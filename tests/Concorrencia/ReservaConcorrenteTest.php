<?php

/*
| Testes de concorrência de verdade: duas conexões MySQL e dois processos.
|
| Usam DatabaseTruncation em vez de RefreshDatabase (ver tests/Pest.php):
| com RefreshDatabase os dados do teste ficam numa transação não confirmada,
| invisível para a outra conexão e para o processo filho.
*/

use App\Actions\ReservarHorario;
use App\Models\Agendamento;
use App\Models\Jornada;
use App\Models\Profissional;
use App\Models\Servico;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['database.connections.mysql_concorrente' => config('database.connections.mysql')]);

    $this->servico = Servico::factory()->create(['duracao_minutos' => 60]);
    $this->profissional = Profissional::factory()->hasAttached($this->servico)->create();

    // Jornada todos os dias: o processo filho usa o relógio real, sem travelTo.
    foreach (range(0, 6) as $dia) {
        Jornada::factory()->for($this->profissional)->create(['dia_semana' => $dia, 'inicio' => '09:00:00', 'fim' => '18:00:00']);
    }

    $this->inicio = CarbonImmutable::today()->addDays(7)->setTime(10, 0);
});

afterEach(function () {
    $outra = DB::connection('mysql_concorrente');
    while ($outra->transactionLevel() > 0) {
        $outra->rollBack();
    }
    DB::purge('mysql_concorrente');
    DB::statement('SET SESSION innodb_lock_wait_timeout = 50');

    // Os dados ficaram confirmados no banco; limpa para não vazar para
    // os testes das outras suítes.
    $this->truncateTablesForAllConnections();
});

/** Abre outra transação, em outra conexão, segurando o lock do profissional. */
function segurarLockDoProfissional(Profissional $profissional): Connection
{
    $outra = DB::connection('mysql_concorrente');
    $outra->beginTransaction();
    $outra->table('profissionais')->where('id', $profissional->id)->lockForUpdate()->first();

    return $outra;
}

/**
 * Inicia a reserva num processo PHP separado e espera ele avisar que vai
 * começar. Devolve o processo e o pipe de saída.
 *
 * @return array{0: resource, 1: resource}
 */
function iniciarReservaEmOutroProcesso(Profissional $profissional, Servico $servico, CarbonImmutable $inicio): array
{
    $processo = proc_open(
        [PHP_BINARY, base_path('tests/Concorrencia/reservar.php'), (string) $profissional->id, (string) $servico->id, $inicio->toIso8601String()],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        base_path(),
        getenv(),
    );

    expect(trim((string) fgets($pipes[1])))->toBe('pronto');

    return [$processo, $pipes[1]];
}

/** @param  resource  $processo */
function aindaRodando($processo): bool
{
    return proc_get_status($processo)['running'];
}

test('a reserva espera o lock e, ao conseguir, enxerga o agendamento confirmado pela outra transação', function () {
    $outra = segurarLockDoProfissional($this->profissional);

    [$processo, $saida] = iniciarReservaEmOutroProcesso($this->profissional, $this->servico, $this->inicio);

    // Uma reserva leva milissegundos; depois de 1 s, se o processo ainda
    // está rodando é porque está parado esperando o lock.
    usleep(1_000_000);
    expect(aindaRodando($processo))->toBeTrue();

    // Enquanto o outro processo espera, esta transação reserva o horário.
    $outra->table('agendamentos')->insert(
        Agendamento::factory()->confirmado()->raw([
            'profissional_id' => $this->profissional->id,
            'servico_id' => $this->servico->id,
            'inicio' => $this->inicio,
            'fim' => $this->inicio->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ])
    );
    $outra->commit();

    $resultado = trim((string) stream_get_contents($saida));
    proc_close($processo);

    expect($resultado)->toBe('indisponivel')
        ->and(Agendamento::count())->toBe(1);
});

test('a reserva espera o lock e prossegue quando a outra transação não ocupa o horário', function () {
    $outra = segurarLockDoProfissional($this->profissional);

    [$processo, $saida] = iniciarReservaEmOutroProcesso($this->profissional, $this->servico, $this->inicio);

    usleep(1_000_000);
    expect(aindaRodando($processo))->toBeTrue();

    $outra->commit();

    $resultado = trim((string) stream_get_contents($saida));
    proc_close($processo);

    expect($resultado)->toStartWith('reservado:')
        ->and(Agendamento::count())->toBe(1);
});

test('o lock é por profissional: não bloqueia reservas de outro profissional', function () {
    $outroProfissional = Profissional::factory()->hasAttached($this->servico)->create();
    foreach (range(0, 6) as $dia) {
        Jornada::factory()->for($outroProfissional)->create(['dia_semana' => $dia, 'inicio' => '09:00:00', 'fim' => '18:00:00']);
    }

    segurarLockDoProfissional($this->profissional);
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    $agendamento = app(ReservarHorario::class)->executar($outroProfissional, $this->servico, $this->inicio, [
        'nome' => 'Cliente',
        'email' => 'cliente@example.com',
        'telefone' => '(11) 91234-5678',
    ]);

    expect($agendamento->profissional_id)->toBe($outroProfissional->id);
});

test('sem conseguir o lock a reserva falha, em vez de gravar sem conferir', function () {
    segurarLockDoProfissional($this->profissional);
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    try {
        app(ReservarHorario::class)->executar($this->profissional, $this->servico, $this->inicio, [
            'nome' => 'Cliente',
            'email' => 'cliente@example.com',
            'telefone' => '(11) 91234-5678',
        ]);
        $this->fail('A reserva deveria ter esperado o lock.');
    } catch (QueryException $erro) {
        // O timeout precisa acontecer no SELECT ... FOR UPDATE, antes de
        // conferir a agenda. Sem o lock explícito, a espera só aconteceria
        // no INSERT (a foreign key pede lock compartilhado no profissional),
        // depois de a conferência já ter sido feita com dados antigos.
        expect($erro->getMessage())->toContain('Lock wait timeout')
            ->and($erro->getSql())->toContain('from `profissionais`')
            ->and($erro->getSql())->toContain('for update');
    }

    expect(Agendamento::count())->toBe(0);
});

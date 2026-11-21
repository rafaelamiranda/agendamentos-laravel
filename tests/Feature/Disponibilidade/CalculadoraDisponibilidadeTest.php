<?php

use App\Models\Agendamento;
use App\Models\Bloqueio;
use App\Models\Jornada;
use App\Models\Profissional;
use App\Models\Servico;
use App\Services\CalculadoraDisponibilidade;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

// Segunda-feira 30/11/2026. "Agora" fica congelado na sexta anterior, para a
// antecedência mínima não interferir, salvo nos testes que tratam dela.
const SEGUNDA = '2026-11-30';

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 08:00'));

    config([
        'agendamentos.antecedencia_minima_minutos' => 120,
        'agendamentos.intervalo_grade_minutos' => 30,
        'agendamentos.antecedencia_maxima_dias' => 60,
    ]);

    $this->servico = Servico::factory()->create(['duracao_minutos' => 60]);
    $this->profissional = Profissional::factory()->hasAttached($this->servico)->create();

    Jornada::factory()->for($this->profissional)->create([
        'dia_semana' => 1,
        'inicio' => '09:00:00',
        'fim' => '18:00:00',
    ]);
});

/** @return list<string> horários livres no formato H:i */
function horariosLivres(Profissional $profissional, Servico $servico, string $data = SEGUNDA): array
{
    return app(CalculadoraDisponibilidade::class)
        ->horariosLivres($profissional, $servico, $data)
        ->map(fn (CarbonImmutable $inicio) => $inicio->format('H:i'))
        ->all();
}

function ocupar(string $classe, Profissional $profissional, string $inicio, string $fim, array $extras = []): void
{
    $classe::factory()->for($profissional)->create([
        'inicio' => CarbonImmutable::parse(SEGUNDA.' '.$inicio),
        'fim' => CarbonImmutable::parse(SEGUNDA.' '.$fim),
        ...$extras,
    ]);
}

test('gera a grade da jornada inteira, terminando exatamente no fim', function () {
    $horarios = horariosLivres($this->profissional, $this->servico);

    expect($horarios)->toHaveCount(17)
        ->and($horarios[0])->toBe('09:00')
        ->and($horarios[1])->toBe('09:30')
        ->and(end($horarios))->toBe('17:00');
});

test('não oferece horário em que o serviço não cabe até o fim da jornada', function () {
    $longo = Servico::factory()->create(['duracao_minutos' => 90]);
    $this->profissional->servicos()->attach($longo);

    expect(horariosLivres($this->profissional, $longo))
        ->toContain('16:30')
        ->not->toContain('17:00');
});

test('respeita o intervalo da grade configurado', function () {
    config(['agendamentos.intervalo_grade_minutos' => 60]);

    expect(horariosLivres($this->profissional, $this->servico))
        ->toBe(['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00']);
});

test('dia sem jornada não tem horários', function () {
    expect(horariosLivres($this->profissional, $this->servico, '2026-12-06'))->toBeEmpty();
});

test('usa a jornada do dia da semana correto', function () {
    Jornada::factory()->for($this->profissional)->create([
        'dia_semana' => 6,
        'inicio' => '09:00:00',
        'fim' => '13:00:00',
    ]);

    expect(horariosLivres($this->profissional, $this->servico, '2026-12-05'))
        ->toBe(['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00']);
});

test('considera mais de uma jornada no mesmo dia e não oferece o intervalo entre elas', function () {
    $this->profissional->jornadas()->delete();
    Jornada::factory()->for($this->profissional)->create(['dia_semana' => 1, 'inicio' => '09:00:00', 'fim' => '11:00:00']);
    Jornada::factory()->for($this->profissional)->create(['dia_semana' => 1, 'inicio' => '14:00:00', 'fim' => '16:00:00']);

    expect(horariosLivres($this->profissional, $this->servico))
        ->toBe(['09:00', '09:30', '10:00', '14:00', '14:30', '15:00']);
});

test('bloqueio remove os horários que o cruzam, mas não os que só encostam', function () {
    ocupar(Bloqueio::class, $this->profissional, '12:00', '13:00');

    expect(horariosLivres($this->profissional, $this->servico))
        ->toContain('11:00', '13:00')
        ->not->toContain('11:30', '12:00', '12:30');
});

test('bloqueio de vários dias (férias) zera a disponibilidade', function () {
    Bloqueio::factory()->for($this->profissional)->create([
        'inicio' => CarbonImmutable::parse('2026-11-28 00:00'),
        'fim' => CarbonImmutable::parse('2026-12-05 00:00'),
        'motivo' => 'Férias',
    ]);

    expect(horariosLivres($this->profissional, $this->servico))->toBeEmpty();
});

test('agendamento confirmado ocupa o horário com intervalo semiaberto', function () {
    ocupar(Agendamento::class, $this->profissional, '10:00', '11:00');

    expect(horariosLivres($this->profissional, $this->servico))
        ->toContain('09:00', '11:00')
        ->not->toContain('09:30', '10:00', '10:30');
});

test('só agendamentos que ocupam horário bloqueiam a agenda', function (string $state, bool $ocupa) {
    Agendamento::factory()->for($this->profissional)->{$state}()->create([
        'inicio' => CarbonImmutable::parse(SEGUNDA.' 10:00'),
        'fim' => CarbonImmutable::parse(SEGUNDA.' 11:00'),
    ]);

    expect(in_array('10:00', horariosLivres($this->profissional, $this->servico)))->toBe(! $ocupa);
})->with([
    'confirmado' => ['confirmado', true],
    'pendente no prazo' => ['pendente', true],
    'pendente vencido' => ['pendenteVencido', false],
    'cancelado' => ['cancelado', false],
    'expirado' => ['expirado', false],
]);

test('agendamentos e bloqueios de outro profissional não interferem', function () {
    $outro = Profissional::factory()->create();
    ocupar(Agendamento::class, $outro, '10:00', '11:00');
    ocupar(Bloqueio::class, $outro, '14:00', '15:00');

    expect(horariosLivres($this->profissional, $this->servico))->toHaveCount(17);
});

test('aplica a antecedência mínima a partir de agora', function (string $agora, string $primeiro) {
    $this->travelTo(CarbonImmutable::parse(SEGUNDA.' '.$agora));

    expect(horariosLivres($this->profissional, $this->servico)[0])->toBe($primeiro);
})->with([
    'limite exato é aceito' => ['10:00', '12:00'],
    'um minuto depois pula para o próximo' => ['10:01', '12:30'],
    'antes da jornada não muda nada' => ['06:00', '09:00'],
]);

test('não há horários depois que a antecedência passa do fim da jornada', function () {
    $this->travelTo(CarbonImmutable::parse(SEGUNDA.' 15:01'));

    expect(horariosLivres($this->profissional, $this->servico))->toBeEmpty();
});

test('data passada não tem horários', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-01 08:00'));

    expect(horariosLivres($this->profissional, $this->servico))->toBeEmpty();
});

test('não há horários quando o profissional não pode atender o serviço', function (string $cenario) {
    match ($cenario) {
        'profissional inativo' => $this->profissional->update(['ativo' => false]),
        'serviço inativo' => $this->servico->update(['ativo' => false]),
        'serviço não vinculado' => $this->profissional->servicos()->detach($this->servico),
    };

    expect(horariosLivres($this->profissional->fresh(), $this->servico->fresh()))->toBeEmpty();
})->with(['profissional inativo', 'serviço inativo', 'serviço não vinculado']);

test('aceita a data como Carbon', function () {
    $horarios = app(CalculadoraDisponibilidade::class)
        ->horariosLivres($this->profissional, $this->servico, CarbonImmutable::parse(SEGUNDA.' 15:45'));

    expect($horarios)->toHaveCount(17)
        ->and($horarios->first()->format('Y-m-d H:i'))->toBe(SEGUNDA.' 09:00');
});

test('o número de consultas não cresce com a quantidade de ocupações', function () {
    foreach (['09:00', '11:00', '13:00', '15:00'] as $hora) {
        ocupar(Agendamento::class, $this->profissional, $hora, CarbonImmutable::parse($hora)->addHour()->format('H:i'));
    }

    DB::enableQueryLog();
    horariosLivres($this->profissional, $this->servico);

    // vínculo com o serviço + jornadas + bloqueios + agendamentos
    expect(DB::getQueryLog())->toHaveCount(4);
});

test('rejeita intervalo de grade inválido', function () {
    new CalculadoraDisponibilidade(antecedenciaMinimaMinutos: 120, intervaloGradeMinutos: 0, antecedenciaMaximaDias: 60);
})->throws(InvalidArgumentException::class);

test('respeita a antecedência máxima, com o último dia incluído', function () {
    // Agora: sexta 27/11/2026. Com 3 dias, a agenda vai até segunda 30/11.
    config(['agendamentos.antecedencia_maxima_dias' => 3]);
    expect(horariosLivres($this->profissional, $this->servico))->toHaveCount(17);

    config(['agendamentos.antecedencia_maxima_dias' => 2]);
    expect(horariosLivres($this->profissional, $this->servico))->toBeEmpty();
});

test('estaLivre aceita só horários da grade que estão livres', function (string $hora, bool $livre) {
    ocupar(Agendamento::class, $this->profissional, '14:00', '15:00');

    $calculadora = app(CalculadoraDisponibilidade::class);

    expect($calculadora->estaLivre($this->profissional, $this->servico, CarbonImmutable::parse(SEGUNDA.' '.$hora)))
        ->toBe($livre);
})->with([
    'livre na grade' => ['10:00', true],
    'fora da grade' => ['10:15', false],
    'ocupado' => ['14:00', false],
    'encosta no ocupado' => ['15:00', true],
    'fora da jornada' => ['18:00', false],
]);

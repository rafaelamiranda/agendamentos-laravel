<?php

use App\Actions\AplicarStatusPagamento;
use App\Enums\SituacaoSinal;
use App\Enums\StatusPagamento;
use App\Jobs\EnviarLembretes;
use App\Mail\AgendamentoCancelado;
use App\Mail\AgendamentoConfirmado;
use App\Mail\LembreteAgendamento;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Pagamento;
use App\Models\Profissional;
use App\Models\Servico;
use App\Pagamentos\PagamentoConsultado;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 10:00'));
    Mail::fake();

    $this->agendamento = Agendamento::factory()
        ->for(Servico::factory()->state(['nome' => 'Corte feminino']))
        ->for(Profissional::factory()->state(['nome' => 'Ana Souza']))
        ->for(Cliente::factory()->state(['nome' => 'Maria', 'email' => 'maria@example.com']))
        ->pendente()
        ->create([
            'inicio' => CarbonImmutable::parse('2026-11-30 10:00'),
            'valor_centavos' => 12000,
            'sinal_centavos' => 3000,
        ]);
});

test('o pagamento aprovado envia o e-mail de confirmação, pela fila', function () {
    Pagamento::factory()->for($this->agendamento)->create(['gateway_id' => '555']);

    app(AplicarStatusPagamento::class)->executar(new PagamentoConsultado('555', StatusPagamento::Aprovado, 3000, now()));

    Mail::assertQueued(AgendamentoConfirmado::class, fn ($mail) => $mail->hasTo('maria@example.com'));
    expect(new AgendamentoConfirmado($this->agendamento))->toBeInstanceOf(ShouldQueue::class);
});

test('um aviso de aprovação repetido não manda a confirmação de novo', function () {
    Pagamento::factory()->for($this->agendamento)->create(['gateway_id' => '555']);
    $aprovado = new PagamentoConsultado('555', StatusPagamento::Aprovado, 3000, now());

    app(AplicarStatusPagamento::class)->executar($aprovado);
    app(AplicarStatusPagamento::class)->executar($aprovado);

    Mail::assertQueuedCount(1);
});

test('a confirmação traz dados, valores e o link assinado de cancelamento', function () {
    $email = new AgendamentoConfirmado($this->agendamento);

    $email->assertHasSubject('Agendamento confirmado: 30/11 às 10:00');
    $email->assertSeeInHtml('Corte feminino');
    $email->assertSeeInHtml('Ana Souza');
    $email->assertSeeInHtml('Segunda-feira, 30 de novembro de 2026 às 10:00');
    $email->assertSeeInHtml('R$ 120,00');
    $email->assertSeeInHtml('R$ 30,00');
    $email->assertSeeInHtml('R$ 90,00');
    $email->assertSeeInHtml('pelo menos 24 horas');
    $email->assertSeeInHtml("/agendamentos/{$this->agendamento->id}/cancelamento?expires=");
    $email->assertSeeInHtml('signature=');
    $email->assertSeeInHtml('Todos os direitos reservados.');
});

test('o e-mail de cancelamento explica o que acontece com o sinal', function (SituacaoSinal $situacao, string $texto) {
    $email = new AgendamentoCancelado($this->agendamento, $situacao);

    $email->assertHasSubject('Agendamento cancelado: 30/11 às 10:00');
    $email->assertSeeInHtml($texto);
})->with([
    [SituacaoSinal::Devolvido, 'será devolvido'],
    [SituacaoSinal::Retido, 'o sinal não é devolvido'],
    [SituacaoSinal::SemPagamento, 'não chegou a ser pago'],
]);

describe('lembretes', function () {
    beforeEach(function () {
        config(['agendamentos.lembrete_horas_antes' => 24]);
        // Reservado com folga (dias antes), como na maioria dos casos.
        // Query direta: created_at não é preenchível pelo model.
        Agendamento::whereKey($this->agendamento->id)->update(['status' => 'confirmado', 'created_at' => now()->subDays(5)]);
    });

    test('envia uma única vez quando faltam até 24 horas', function () {
        $this->travelTo(CarbonImmutable::parse('2026-11-29 09:59'));
        (new EnviarLembretes)->handle();
        Mail::assertNothingQueued();

        $this->travelTo(CarbonImmutable::parse('2026-11-29 10:00'));
        (new EnviarLembretes)->handle();
        (new EnviarLembretes)->handle();

        Mail::assertQueued(LembreteAgendamento::class, 1);
        expect($this->agendamento->fresh()->lembrete_enviado_em)->not->toBeNull();
    });

    test('lembrete atrasado (agendador parado) ainda sai antes do horário', function () {
        $this->travelTo(CarbonImmutable::parse('2026-11-30 08:00'));
        (new EnviarLembretes)->handle();

        Mail::assertQueued(LembreteAgendamento::class, 1);
    });

    test('não envia para pendentes, cancelados ou horários que já passaram', function (string $status, string $agora) {
        Agendamento::whereKey($this->agendamento->id)->update(['status' => $status]);
        $this->travelTo(CarbonImmutable::parse($agora));

        (new EnviarLembretes)->handle();

        Mail::assertNothingQueued();
    })->with([
        ['pendente', '2026-11-29 12:00'],
        ['cancelado', '2026-11-29 12:00'],
        ['confirmado', '2026-11-30 10:01'],
    ]);

    test('não envia lembrete para quem reservou já dentro das 24 horas', function () {
        Agendamento::whereKey($this->agendamento->id)->update(['created_at' => CarbonImmutable::parse('2026-11-29 18:00')]);
        $this->travelTo(CarbonImmutable::parse('2026-11-29 18:15'));

        (new EnviarLembretes)->handle();

        Mail::assertNothingQueued();
    });

    test('o lembrete tem os dados e o link de cancelamento', function () {
        $email = new LembreteAgendamento($this->agendamento);

        $email->assertSeeInHtml('Seu horário está chegando');
        $email->assertSeeInHtml('R$ 90,00');
        $email->assertSeeInHtml('signature=');
    });

    test('está agendado a cada 15 minutos', function () {
        $evento = collect(app(Schedule::class)->events())
            ->first(fn ($evento) => str_contains((string) $evento->description, EnviarLembretes::class));

        expect($evento?->expression)->toBe('*/15 * * * *')
            ->and($evento->withoutOverlapping)->toBeTrue();
    });
});

test('a mensagem resumida de validação sai em português', function () {
    $this->postJson(route('api.agendamentos.store'), [])
        ->assertJsonPath('message', 'O campo profissional é obrigatório. (e mais 6 erros)');
});

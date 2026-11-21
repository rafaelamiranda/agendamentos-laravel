<?php

use App\Actions\CancelarAgendamento;
use App\Enums\OrigemCancelamento;
use App\Enums\SituacaoSinal;
use App\Enums\StatusAgendamento;
use App\Exceptions\AgendamentoNaoCancelavelException;
use App\Jobs\EstornarPagamento;
use App\Mail\AgendamentoCancelado;
use App\Models\Agendamento;
use App\Models\Pagamento;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 10:00'));
    config(['agendamentos.prazo_cancelamento_horas' => 24]);
    Queue::fake([EstornarPagamento::class]);
    Mail::fake();
});

/** Agendamento confirmado, com o sinal pago, começando em $inicio. */
function agendamentoPago(string $inicio): Agendamento
{
    $agendamento = Agendamento::factory()->confirmado()->create(['inicio' => CarbonImmutable::parse($inicio)]);
    Pagamento::factory()->for($agendamento)->aprovado()->create();

    return $agendamento;
}

function cancelar(Agendamento $agendamento, OrigemCancelamento $origem = OrigemCancelamento::Cliente): SituacaoSinal
{
    return app(CancelarAgendamento::class)->executar($agendamento, $origem);
}

test('cliente cancelando dentro do prazo recebe o sinal de volta', function () {
    $agendamento = agendamentoPago('2026-11-29 10:00');

    expect(cancelar($agendamento))->toBe(SituacaoSinal::Devolvido);

    expect($agendamento->fresh())
        ->status->toBe(StatusAgendamento::Cancelado)
        ->cancelado_por->toBe(OrigemCancelamento::Cliente)
        ->cancelado_em->not->toBeNull()
        ->and($agendamento->pagamento->fresh()->requer_estorno)->toBeTrue();

    Queue::assertPushed(EstornarPagamento::class, fn ($job) => $job->pagamentoId === $agendamento->pagamento->id);
    Mail::assertQueued(AgendamentoCancelado::class, fn ($mail) => $mail->hasTo($agendamento->cliente->email)
        && $mail->situacaoSinal === SituacaoSinal::Devolvido);
});

test('o limite exato de 24 horas ainda devolve; um minuto depois, não', function () {
    $noLimite = agendamentoPago('2026-11-28 10:00');
    $depois = agendamentoPago('2026-11-28 09:59');

    expect(cancelar($noLimite))->toBe(SituacaoSinal::Devolvido)
        ->and(cancelar($depois))->toBe(SituacaoSinal::Retido);
});

test('cliente cancelando fora do prazo libera o horário, mas o sinal fica retido', function () {
    $agendamento = agendamentoPago('2026-11-27 15:00');

    expect(cancelar($agendamento))->toBe(SituacaoSinal::Retido);

    expect($agendamento->fresh()->status)->toBe(StatusAgendamento::Cancelado)
        ->and($agendamento->pagamento->fresh()->requer_estorno)->toBeFalse();
    Queue::assertNotPushed(EstornarPagamento::class);
    Mail::assertQueued(AgendamentoCancelado::class, fn ($mail) => $mail->situacaoSinal === SituacaoSinal::Retido);
});

test('o estabelecimento cancelando devolve o sinal mesmo fora do prazo', function () {
    $agendamento = agendamentoPago('2026-11-27 15:00');

    expect(cancelar($agendamento, OrigemCancelamento::Estabelecimento))->toBe(SituacaoSinal::Devolvido);

    expect($agendamento->fresh()->cancelado_por)->toBe(OrigemCancelamento::Estabelecimento);
    Queue::assertPushed(EstornarPagamento::class);
});

test('reserva pendente (sinal não pago) é cancelada sem estorno', function () {
    $agendamento = Agendamento::factory()->pendente()->create(['inicio' => CarbonImmutable::parse('2026-11-29 10:00')]);
    Pagamento::factory()->for($agendamento)->create();

    expect(cancelar($agendamento))->toBe(SituacaoSinal::SemPagamento);

    Queue::assertNotPushed(EstornarPagamento::class);
});

test('o horário cancelado volta para a disponibilidade', function () {
    $agendamento = agendamentoPago('2026-11-29 10:00');
    cancelar($agendamento);

    expect(Agendamento::ocupandoHorario()->whereKey($agendamento->id)->exists())->toBeFalse();
});

test('não cancela o que já foi cancelado, concluído, expirado ou já começou', function (string $cenario) {
    $agendamento = match ($cenario) {
        'cancelado' => Agendamento::factory()->cancelado()->create(['inicio' => now()->addDays(3)]),
        'concluído' => Agendamento::factory()->concluido()->create(),
        'expirado' => Agendamento::factory()->expirado()->create(['inicio' => now()->addDays(3)]),
        'já começou' => Agendamento::factory()->confirmado()->create(['inicio' => now()->subMinutes(10)]),
    };

    expect(fn () => cancelar($agendamento))->toThrow(AgendamentoNaoCancelavelException::class);

    Queue::assertNotPushed(EstornarPagamento::class);
    Mail::assertNothingQueued();
})->with(['cancelado', 'concluído', 'expirado', 'já começou']);

test('cancelar duas vezes não estorna duas vezes', function () {
    $agendamento = agendamentoPago('2026-11-29 10:00');

    cancelar($agendamento);
    expect(fn () => cancelar($agendamento))->toThrow(AgendamentoNaoCancelavelException::class);

    Queue::assertPushedTimes(EstornarPagamento::class, 1);
});

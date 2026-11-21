<?php

use App\Actions\AplicarStatusPagamento;
use App\Enums\StatusAgendamento;
use App\Enums\StatusPagamento;
use App\Jobs\EstornarPagamento;
use App\Models\Agendamento;
use App\Models\Pagamento;
use App\Pagamentos\PagamentoConsultado;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->freezeTime();
    Queue::fake([EstornarPagamento::class]);
    Mail::fake();

    $this->agendamento = Agendamento::factory()->pendente()->create(['sinal_centavos' => 3000]);
    $this->pagamento = Pagamento::factory()->for($this->agendamento)->create(['gateway_id' => '555']);
});

function aplicarStatus(StatusPagamento $status, int $valor = 3000, ?CarbonImmutable $aprovadoEm = null): void
{
    app(AplicarStatusPagamento::class)->executar(new PagamentoConsultado('555', $status, $valor, $aprovadoEm ?? now()));
}

test('pagamento aprovado confirma o agendamento pendente', function () {
    aplicarStatus(StatusPagamento::Aprovado);

    expect($this->pagamento->fresh())
        ->status->toBe(StatusPagamento::Aprovado)
        ->aprovado_em->not->toBeNull()
        ->requer_estorno->toBeFalse()
        ->and($this->agendamento->fresh()->status)->toBe(StatusAgendamento::Confirmado);

    Queue::assertNotPushed(EstornarPagamento::class);
});

test('o mesmo aviso aplicado duas vezes não muda nada na segunda', function () {
    aplicarStatus(StatusPagamento::Aprovado, aprovadoEm: now());
    $aprovadoEm = $this->pagamento->fresh()->aprovado_em;

    $this->travel(5)->minutes();
    aplicarStatus(StatusPagamento::Aprovado, aprovadoEm: now());

    expect($this->pagamento->fresh()->aprovado_em->equalTo($aprovadoEm))->toBeTrue()
        ->and($this->agendamento->fresh()->status)->toBe(StatusAgendamento::Confirmado);
});

test('um pendente atrasado não desfaz um aprovado', function () {
    aplicarStatus(StatusPagamento::Aprovado);
    aplicarStatus(StatusPagamento::Pendente);

    expect($this->pagamento->fresh()->status)->toBe(StatusPagamento::Aprovado);
});

test('pagamento recusado não confirma o agendamento', function () {
    aplicarStatus(StatusPagamento::Recusado);

    expect($this->pagamento->fresh()->status)->toBe(StatusPagamento::Recusado)
        ->and($this->agendamento->fresh()->status)->toBe(StatusAgendamento::Pendente);
});

test('valor pago diferente do sinal não confirma e marca para estorno', function () {
    aplicarStatus(StatusPagamento::Aprovado, valor: 100);

    expect($this->pagamento->fresh())
        ->status->toBe(StatusPagamento::Aprovado)
        ->requer_estorno->toBeTrue()
        ->and($this->agendamento->fresh()->status)->toBe(StatusAgendamento::Pendente);

    Queue::assertPushed(EstornarPagamento::class, fn ($job) => $job->pagamentoId === $this->pagamento->id);
});

test('pagamento que chega depois da expiração confirma se o horário continua livre', function () {
    $this->agendamento->update(['status' => StatusAgendamento::Expirado, 'expira_em' => now()->subMinute()]);

    aplicarStatus(StatusPagamento::Aprovado);

    expect($this->agendamento->fresh()->status)->toBe(StatusAgendamento::Confirmado)
        ->and($this->pagamento->fresh()->requer_estorno)->toBeFalse();
});

test('pagamento que chega depois da expiração com o horário já ocupado marca para estorno', function () {
    $this->agendamento->update(['status' => StatusAgendamento::Expirado, 'expira_em' => now()->subMinute()]);
    Agendamento::factory()->confirmado()->create([
        'profissional_id' => $this->agendamento->profissional_id,
        'inicio' => $this->agendamento->inicio,
        'fim' => $this->agendamento->fim,
    ]);

    aplicarStatus(StatusPagamento::Aprovado);

    expect($this->agendamento->fresh()->status)->toBe(StatusAgendamento::Expirado)
        ->and($this->pagamento->fresh()->requer_estorno)->toBeTrue();

    Queue::assertPushed(EstornarPagamento::class, fn ($job) => $job->pagamentoId === $this->pagamento->id);
});

test('pagamento aprovado para agendamento cancelado marca para estorno', function () {
    $this->agendamento->update(['status' => StatusAgendamento::Cancelado, 'cancelado_em' => now()]);

    aplicarStatus(StatusPagamento::Aprovado);

    expect($this->agendamento->fresh()->status)->toBe(StatusAgendamento::Cancelado)
        ->and($this->pagamento->fresh()->requer_estorno)->toBeTrue();

    Queue::assertPushed(EstornarPagamento::class, fn ($job) => $job->pagamentoId === $this->pagamento->id);
});

test('ignora pagamento que o sistema não conhece', function () {
    app(AplicarStatusPagamento::class)->executar(new PagamentoConsultado('999', StatusPagamento::Aprovado, 3000, now()));

    expect($this->agendamento->fresh()->status)->toBe(StatusAgendamento::Pendente);
});

test('aviso de estorno feito (até direto no painel do Mercado Pago) limpa a pendência', function () {
    $this->pagamento->update(['status' => StatusPagamento::Aprovado, 'requer_estorno' => true]);

    aplicarStatus(StatusPagamento::Estornado);

    expect($this->pagamento->fresh())
        ->status->toBe(StatusPagamento::Estornado)
        ->estornado_em->not->toBeNull()
        ->requer_estorno->toBeFalse();
});

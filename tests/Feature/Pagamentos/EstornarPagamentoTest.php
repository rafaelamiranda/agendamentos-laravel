<?php

use App\Enums\StatusPagamento;
use App\Jobs\EstornarPagamento;
use App\Models\Pagamento;
use App\Pagamentos\FalhaGatewayPagamentoException;
use App\Pagamentos\GatewayPix;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Sleep::fake();
    $this->pagamento = Pagamento::factory()->aprovado()->create(['gateway_id' => '555', 'requer_estorno' => true]);
});

test('estorna no Mercado Pago com chave de idempotência e marca como estornado', function () {
    Http::fake(['api.mercadopago.com/v1/payments/555/refunds' => Http::response(['id' => 1, 'status' => 'approved'], 201)]);

    (new EstornarPagamento($this->pagamento->id))->handle(app(GatewayPix::class));

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://api.mercadopago.com/v1/payments/555/refunds'
        && $request->hasHeader('X-Idempotency-Key', "estorno-pagamento-{$this->pagamento->id}"));

    expect($this->pagamento->fresh())
        ->status->toBe(StatusPagamento::Estornado)
        ->estornado_em->not->toBeNull()
        ->requer_estorno->toBeFalse();
});

test('pagamento já estornado não gera nova chamada', function () {
    Http::fake();
    $this->pagamento->update(['status' => StatusPagamento::Estornado]);

    EstornarPagamento::dispatchSync($this->pagamento->id);

    Http::assertNothingSent();
});

test('falha no gateway mantém a pendência e deixa a fila tentar de novo', function () {
    Http::fake(['api.mercadopago.com/*' => Http::response(['message' => 'erro'], 500)]);

    expect(fn () => EstornarPagamento::dispatchSync($this->pagamento->id))
        ->toThrow(FalhaGatewayPagamentoException::class);

    expect($this->pagamento->fresh())
        ->status->toBe(StatusPagamento::Aprovado)
        ->requer_estorno->toBeTrue();
});

test('o job tenta 5 vezes com intervalo crescente', function () {
    $job = new EstornarPagamento($this->pagamento->id);

    expect($job->tries)->toBe(5)
        ->and($job->backoff())->toBe([30, 120, 600, 1800]);
});

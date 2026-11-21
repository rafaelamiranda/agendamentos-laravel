<?php

use App\Enums\StatusPagamento;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Servico;
use App\Pagamentos\FalhaGatewayPagamentoException;
use App\Pagamentos\GatewayPix;
use App\Pagamentos\MercadoPagoGateway;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 08:00'));
    Sleep::fake();

    $this->agendamento = Agendamento::factory()
        ->for(Servico::factory()->state(['nome' => 'Corte feminino', 'sinal_centavos' => 3050]))
        ->for(Cliente::factory()->state(['nome' => 'Maria Silva', 'email' => 'maria@example.com']))
        ->pendente()
        ->create(['inicio' => CarbonImmutable::parse('2026-11-30 10:00')]);

    $this->gateway = app(GatewayPix::class);
});

test('o container entrega o Mercado Pago como gateway Pix', function () {
    expect($this->gateway)->toBeInstanceOf(MercadoPagoGateway::class);
});

test('cria a cobrança Pix com valor em reais, vencimento e chave de idempotência', function () {
    Http::fake(['api.mercadopago.com/v1/payments' => Http::response(respostaPagamentoMercadoPago(), 201)]);

    $cobranca = $this->gateway->criarCobranca($this->agendamento);

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.mercadopago.com/v1/payments'
            && $request->hasHeader('Authorization', 'Bearer TEST-token-de-teste')
            && $request->hasHeader('X-Idempotency-Key', "agendamento-{$this->agendamento->id}")
            && $request['transaction_amount'] === 30.5
            && $request['payment_method_id'] === 'pix'
            && $request['external_reference'] === (string) $this->agendamento->id
            && $request['notification_url'] === 'https://agendamentos.test/api/webhooks/mercadopago'
            && $request['date_of_expiration'] === '2026-11-27T08:15:00.000-03:00'
            && $request['description'] === 'Sinal: Corte feminino em 30/11/2026 10:00'
            && $request['payer'] === ['email' => 'maria@example.com', 'first_name' => 'Maria Silva'];
    });

    expect($cobranca->gatewayId)->toBe('1325461234')
        ->and($cobranca->status)->toBe(StatusPagamento::Pendente)
        ->and($cobranca->copiaECola)->toStartWith('000201')
        ->and($cobranca->qrCodeBase64)->toBe('iVBORw0KGgoAAAANSUhEUgAAAAE=')
        // 07:15 em -04:00 é 08:15 em Brasília.
        ->and($cobranca->expiraEm->format('Y-m-d H:i P'))->toBe('2026-11-27 08:15 -03:00');
});

test('tenta de novo em erro 5xx, sempre com a mesma chave de idempotência', function () {
    Http::fake(['api.mercadopago.com/v1/payments' => Http::sequence()
        ->push(['message' => 'erro interno'], 500)
        ->push(respostaPagamentoMercadoPago(), 201)]);

    $cobranca = $this->gateway->criarCobranca($this->agendamento);

    expect($cobranca->gatewayId)->toBe('1325461234');
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => $request->hasHeader('X-Idempotency-Key', "agendamento-{$this->agendamento->id}"));
});

test('não tenta de novo em erro 4xx', function () {
    Http::fake(['api.mercadopago.com/v1/payments' => Http::response(['message' => 'invalid'], 400)]);

    expect(fn () => $this->gateway->criarCobranca($this->agendamento))
        ->toThrow(FalhaGatewayPagamentoException::class, 'Mercado Pago respondeu 400');

    Http::assertSentCount(1);
});

test('falha de conexão vira FalhaGatewayPagamentoException depois das tentativas', function () {
    Http::fake(['api.mercadopago.com/*' => fn () => throw new ConnectionException('timeout')]);

    expect(fn () => $this->gateway->criarCobranca($this->agendamento))
        ->toThrow(FalhaGatewayPagamentoException::class, 'sem resposta do Mercado Pago');
});

test('consulta o pagamento e converte reais para centavos', function () {
    Http::fake(['api.mercadopago.com/v1/payments/1325461234' => Http::response(respostaPagamentoMercadoPago([
        'status' => 'approved',
        'transaction_amount' => 30.5,
        'date_approved' => '2026-11-27T07:05:00.000-04:00',
    ]))]);

    $consultado = $this->gateway->consultarPagamento('1325461234');

    expect($consultado->gatewayId)->toBe('1325461234')
        ->and($consultado->status)->toBe(StatusPagamento::Aprovado)
        ->and($consultado->valorCentavos)->toBe(3050)
        ->and($consultado->aprovadoEm?->format('Y-m-d H:i'))->toBe('2026-11-27 08:05');
});

test('mapeia os status do Mercado Pago para o domínio', function (string $statusMercadoPago, StatusPagamento $esperado) {
    Http::fake(['api.mercadopago.com/*' => Http::response(respostaPagamentoMercadoPago(['status' => $statusMercadoPago]))]);

    expect($this->gateway->consultarPagamento('1325461234')->status)->toBe($esperado);
})->with([
    ['pending', StatusPagamento::Pendente],
    ['in_process', StatusPagamento::Pendente],
    ['approved', StatusPagamento::Aprovado],
    ['rejected', StatusPagamento::Recusado],
    ['cancelled', StatusPagamento::Cancelado],
    ['refunded', StatusPagamento::Estornado],
    ['charged_back', StatusPagamento::Estornado],
    'desconhecido fica pendente' => ['status_novo', StatusPagamento::Pendente],
]);

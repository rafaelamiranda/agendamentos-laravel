<?php

use App\Enums\StatusAgendamento;
use App\Jobs\ProcessarNotificacaoPagamento;
use App\Models\Agendamento;
use App\Models\Pagamento;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->freezeTime();
});

/**
 * Envia um aviso como o Mercado Pago envia: data.id e type na query
 * string, o mesmo conteúdo no corpo e a assinatura nos headers.
 *
 * @param  array<string, string>  $headers
 */
function enviarWebhook(string $dataId = '555', string $tipo = 'payment', ?array $headers = null): TestResponse
{
    $headers ??= [
        'x-request-id' => 'req-123',
        'x-signature' => assinaturaWebhook($dataId, 'req-123', now()->getTimestamp()),
    ];

    return test()->postJson(
        "/api/webhooks/mercadopago?data.id={$dataId}&type={$tipo}",
        ['action' => 'payment.updated', 'type' => $tipo, 'data' => ['id' => $dataId]],
        $headers,
    );
}

test('aviso assinado é enfileirado e respondido com 200', function () {
    Queue::fake();

    enviarWebhook()->assertOk()->assertJson(['recebido' => true, 'processado' => true]);

    Queue::assertPushed(ProcessarNotificacaoPagamento::class, fn ($job) => $job->gatewayId === '555');
});

test('recusa aviso sem assinatura ou com assinatura inválida', function (array $headers) {
    Queue::fake();

    enviarWebhook(headers: $headers)->assertUnauthorized();

    Queue::assertNothingPushed();
})->with([
    'sem headers' => [[]],
    'assinatura de outro segredo' => [['x-request-id' => 'req-123', 'x-signature' => 'ts='.time().',v1='.hash_hmac('sha256', 'qualquer', 'outro-segredo')]],
    'assinatura de outro pagamento' => [['x-request-id' => 'req-123', 'x-signature' => assinaturaWebhook('999', 'req-123', time())]],
    'formato quebrado' => [['x-request-id' => 'req-123', 'x-signature' => 'lixo']],
]);

test('recusa aviso com horário fora da tolerância (replay)', function () {
    Queue::fake();
    $antigo = now()->subMinutes(10)->getTimestamp();

    enviarWebhook(headers: [
        'x-request-id' => 'req-123',
        'x-signature' => assinaturaWebhook('555', 'req-123', $antigo),
    ])->assertUnauthorized();
});

test('aceita o ts em milissegundos', function () {
    Queue::fake();
    $ms = now()->getTimestamp() * 1000;

    enviarWebhook(headers: [
        'x-request-id' => 'req-123',
        'x-signature' => assinaturaWebhook('555', 'req-123', $ms),
    ])->assertOk();
});

test('sem segredo configurado, recusa tudo', function () {
    config(['services.mercadopago.webhook_secret' => null]);

    enviarWebhook()->assertUnauthorized();
});

test('ignora tópicos que não são pagamento', function () {
    Queue::fake();

    enviarWebhook(tipo: 'merchant_order')->assertOk()->assertJson(['processado' => false]);

    Queue::assertNothingPushed();
});

test('fluxo completo: aviso de pagamento aprovado confirma o agendamento uma única vez', function () {
    $agendamento = Agendamento::factory()->pendente()->create(['sinal_centavos' => 3000]);
    Pagamento::factory()->for($agendamento)->create(['gateway_id' => '555']);

    Http::fake(['api.mercadopago.com/v1/payments/555' => Http::response(respostaPagamentoMercadoPago([
        'id' => 555,
        'status' => 'approved',
        'transaction_amount' => 30,
        'date_approved' => now()->toIso8601String(),
    ]))]);

    // A fila é síncrona nos testes (phpunit.xml): o job roda na hora.
    enviarWebhook()->assertOk();
    enviarWebhook()->assertOk();

    expect($agendamento->fresh()->status)->toBe(StatusAgendamento::Confirmado);

    // O corpo do webhook não decide nada: cada aviso consultou a API.
    Http::assertSentCount(2);
});

<?php

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Concorrência precisa de dados confirmados (visíveis para outras conexões
// e processos), então trunca as tabelas em vez de usar transação.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concorrencia');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Corpo de resposta do Mercado Pago para POST/GET /v1/payments, no formato
 * da documentação, com os campos que o sistema usa.
 *
 * @param  array<string, mixed>  $sobrescrever
 * @return array<string, mixed>
 */
function respostaPagamentoMercadoPago(array $sobrescrever = []): array
{
    return array_replace_recursive([
        'id' => 1325461234,
        'status' => 'pending',
        'status_detail' => 'pending_waiting_transfer',
        'transaction_amount' => 30,
        'date_approved' => null,
        // O Mercado Pago costuma responder em -04:00; o sistema converte.
        'date_of_expiration' => '2026-11-27T07:15:00.000-04:00',
        'external_reference' => '1',
        'point_of_interaction' => [
            'transaction_data' => [
                'qr_code' => '00020126580014br.gov.bcb.pix0136chave-pix-teste5204000053039865802BR6304ABCD',
                'qr_code_base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAE=',
                'ticket_url' => 'https://www.mercadopago.com.br/payments/1325461234/ticket',
            ],
        ],
    ], $sobrescrever);
}

/** Header x-signature válido para o segredo dos testes (phpunit.xml). */
function assinaturaWebhook(string $dataId, string $requestId, int $ts): string
{
    $manifesto = 'id:'.strtolower($dataId).";request-id:{$requestId};ts:{$ts};";

    return "ts={$ts},v1=".hash_hmac('sha256', $manifesto, 'segredo-de-teste');
}

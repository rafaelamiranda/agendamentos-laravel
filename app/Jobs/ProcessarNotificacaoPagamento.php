<?php

namespace App\Jobs;

use App\Actions\AplicarStatusPagamento;
use App\Pagamentos\GatewayPix;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Processa um aviso de pagamento fora da requisição do webhook. O Mercado
 * Pago espera resposta rápida e reenvia em caso de timeout; aqui, se a API
 * dele falhar, a fila tenta de novo com intervalo crescente.
 */
class ProcessarNotificacaoPagamento implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public readonly string $gatewayId) {}

    /** @return list<int> segundos entre as tentativas */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(GatewayPix $gateway, AplicarStatusPagamento $aplicar): void
    {
        // O webhook só avisa que algo mudou. O estado vem da API: o corpo da
        // notificação não é usado para decidir nada.
        $aplicar->executar($gateway->consultarPagamento($this->gatewayId));
    }
}

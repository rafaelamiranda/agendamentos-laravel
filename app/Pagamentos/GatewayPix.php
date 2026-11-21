<?php

namespace App\Pagamentos;

use App\Models\Agendamento;

/**
 * Contrato do provedor de Pix. O domínio (reserva, webhook, painel) depende
 * só desta interface; trocar de provedor é escrever outra implementação.
 */
interface GatewayPix
{
    /**
     * Cria a cobrança Pix do sinal do agendamento. Deve ser idempotente:
     * chamar de novo para o mesmo agendamento não gera uma segunda cobrança.
     *
     * @throws FalhaGatewayPagamentoException
     */
    public function criarCobranca(Agendamento $agendamento): CobrancaPix;

    /**
     * Estado atual do pagamento no provedor. É a fonte da verdade, e não o
     * corpo do webhook, que qualquer um poderia forjar.
     *
     * @throws FalhaGatewayPagamentoException
     */
    public function consultarPagamento(string $gatewayId): PagamentoConsultado;

    /**
     * Devolve o valor total do pagamento. A chave de idempotência garante
     * que repetir a chamada (retry da fila) não estorne duas vezes.
     *
     * @throws FalhaGatewayPagamentoException
     */
    public function estornar(string $gatewayId, string $chaveIdempotencia): void;
}

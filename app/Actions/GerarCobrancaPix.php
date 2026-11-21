<?php

namespace App\Actions;

use App\Models\Agendamento;
use App\Models\Pagamento;
use App\Pagamentos\FalhaGatewayPagamentoException;
use App\Pagamentos\GatewayPix;

class GerarCobrancaPix
{
    public function __construct(private readonly GatewayPix $gateway) {}

    /**
     * Idempotente: se o agendamento já tem cobrança, devolve a existente.
     *
     * @throws FalhaGatewayPagamentoException
     */
    public function executar(Agendamento $agendamento): Pagamento
    {
        if ($agendamento->pagamento) {
            return $agendamento->pagamento;
        }

        $cobranca = $this->gateway->criarCobranca($agendamento);

        $pagamento = $agendamento->pagamento()->create([
            'gateway' => 'mercadopago',
            'gateway_id' => $cobranca->gatewayId,
            'status' => $cobranca->status,
            'valor_centavos' => $agendamento->sinal_centavos,
            'pix_copia_e_cola' => $cobranca->copiaECola,
            'pix_qr_code_base64' => $cobranca->qrCodeBase64,
            'expira_em' => $cobranca->expiraEm,
        ]);

        $agendamento->setRelation('pagamento', $pagamento);

        return $pagamento;
    }
}

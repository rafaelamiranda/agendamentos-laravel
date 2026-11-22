<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessarNotificacaoPagamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Webhooks
 */
class WebhookMercadoPagoController extends Controller
{
    /**
     * Aviso de pagamento do Mercado Pago
     *
     * Chamado pelo Mercado Pago, não pelo cliente. Só enfileira e responde
     * 200: o Mercado Pago espera resposta em poucos segundos e reenvia se não
     * receber. O trabalho pesado (consultar a API, atualizar a agenda) fica no
     * job.
     *
     * O corpo é tratado só como sinal: o job consulta o pagamento na API do
     * Mercado Pago, que é a fonte da verdade. Avisos repetidos ou fora de ordem
     * não mudam nada (processamento idempotente).
     *
     * A assinatura é HMAC-SHA256 de `id:{data.id};request-id:{x-request-id};ts:{ts};`
     * com o segredo do webhook; avisos com `ts` a mais de 5 minutos do horário
     * do servidor são recusados.
     *
     * @header x-signature required Assinatura no formato ts={timestamp},v1={hmac}. Example: ts=1764252000,v1=5f1c...e9
     * @header x-request-id Id do aviso enviado pelo Mercado Pago. Example: bb56a2f1-6aae-46ac-982e-9dcd3581d08e
     *
     * @queryParam data.id string required Id do pagamento no Mercado Pago. Example: 1325461234
     * @queryParam type string required Tópico do aviso; só `payment` é processado. Example: payment
     *
     * @bodyParam type string Tópico do aviso. Example: payment
     * @bodyParam data object Recurso que mudou.
     * @bodyParam data.id string Id do pagamento. Example: 1325461234
     *
     * @response 200 scenario="Aviso aceito e enfileirado" {"recebido": true, "processado": true}
     * @response 200 scenario="Tópico ignorado (ex.: merchant_order)" {"recebido": true, "processado": false}
     * @response 401 scenario="Assinatura ausente, inválida ou antiga" {"message": "Assinatura inválida."}
     */
    public function __invoke(Request $request): JsonResponse
    {
        $tipo = $request->query('type', $request->input('type'));
        $gatewayId = $request->query('data_id', $request->input('data.id'));

        // Outros tópicos (merchant_order etc.) não interessam: 200 para o
        // Mercado Pago não ficar reenviando.
        if ($tipo !== 'payment' || ! is_scalar($gatewayId) || (string) $gatewayId === '') {
            return response()->json(['recebido' => true, 'processado' => false]);
        }

        ProcessarNotificacaoPagamento::dispatch((string) $gatewayId);

        return response()->json(['recebido' => true, 'processado' => true]);
    }
}

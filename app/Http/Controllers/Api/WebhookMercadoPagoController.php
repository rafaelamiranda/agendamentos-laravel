<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessarNotificacaoPagamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookMercadoPagoController extends Controller
{
    /**
     * Só enfileira e responde 200. O Mercado Pago espera resposta em poucos
     * segundos e reenvia se não receber; o trabalho pesado (consultar a
     * API, atualizar a agenda) fica no job.
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

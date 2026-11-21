<?php

namespace App\Pagamentos;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

/**
 * O provedor de pagamento não respondeu ou recusou a chamada. No webhook,
 * a exceção faz o job ser tentado de novo; na reserva, vira 503.
 */
class FalhaGatewayPagamentoException extends RuntimeException
{
    public function __construct(string $detalhe, ?Throwable $previous = null)
    {
        parent::__construct("Falha no gateway de pagamento: {$detalhe}", 0, $previous);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Não foi possível gerar o Pix agora. Tente reservar novamente em instantes.',
        ], 503);
    }
}

<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * O horário pedido não pode ser reservado: fora da grade, da jornada ou da
 * antecedência, ou ocupado (às vezes por alguém que reservou um instante antes).
 */
class HorarioIndisponivelException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('O horário escolhido não está disponível. Consulte os horários livres e tente outro.');
    }

    /**
     * 409 Conflict, e não 422: o pedido é válido, mas conflita com o estado
     * atual da agenda. O cliente pode corrigir escolhendo outro horário.
     */
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}

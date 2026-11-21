<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConsultarDisponibilidadeRequest;
use App\Models\Profissional;
use App\Services\CalculadoraDisponibilidade;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class DisponibilidadeController extends Controller
{
    public function __invoke(
        ConsultarDisponibilidadeRequest $request,
        Profissional $profissional,
        CalculadoraDisponibilidade $calculadora,
    ): JsonResponse {
        $servico = $request->servico();

        $horarios = $calculadora
            ->horariosLivres($profissional, $servico, $request->string('data')->toString())
            ->map(fn (CarbonImmutable $inicio) => [
                'inicio' => $inicio->toIso8601String(),
                'fim' => $inicio->addMinutes($servico->duracao_minutos)->toIso8601String(),
            ]);

        return response()->json(['data' => $horarios]);
    }
}

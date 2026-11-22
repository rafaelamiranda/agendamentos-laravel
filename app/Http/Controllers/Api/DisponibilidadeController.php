<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConsultarDisponibilidadeRequest;
use App\Models\Profissional;
use App\Services\CalculadoraDisponibilidade;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * @group Disponibilidade
 */
class DisponibilidadeController extends Controller
{
    /**
     * Listar horários livres
     *
     * Horários em que o profissional pode atender o serviço na data, já
     * descontando jornada, bloqueios (folgas, almoço), agendamentos e a
     * antecedência mínima (2 h) e máxima (60 dias). A grade é de 30 em 30
     * minutos.
     *
     * A lista é informativa: entre a consulta e a reserva, outra pessoa pode
     * reservar o mesmo horário. Por isso a reserva confere de novo e pode
     * responder 409.
     *
     * @urlParam profissional integer required Id do profissional. Example: 1
     *
     * @response 200 scenario="Horários livres" {"data": [{"inicio": "2026-11-30T09:00:00-03:00", "fim": "2026-11-30T10:00:00-03:00"}, {"inicio": "2026-11-30T09:30:00-03:00", "fim": "2026-11-30T10:30:00-03:00"}, {"inicio": "2026-11-30T11:00:00-03:00", "fim": "2026-11-30T12:00:00-03:00"}]}
     * @response 200 scenario="Dia sem jornada, profissional inativo ou que não realiza o serviço" {"data": []}
     * @response 404 scenario="Profissional não encontrado" {"message": "Registro não encontrado."}
     * @response 422 scenario="Parâmetros inválidos" {"message": "O campo data deve estar no formato Y-m-d.", "errors": {"data": ["O campo data deve estar no formato Y-m-d."]}}
     */
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

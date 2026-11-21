<?php

namespace App\Http\Controllers\Api;

use App\Actions\ReservarHorario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CriarAgendamentoRequest;
use App\Http\Resources\AgendamentoResource;
use Illuminate\Http\JsonResponse;

class AgendamentoController extends Controller
{
    public function store(CriarAgendamentoRequest $request, ReservarHorario $reservar): JsonResponse
    {
        $agendamento = $reservar->executar(
            $request->profissional(),
            $request->servico(),
            $request->inicio(),
            $request->dadosCliente(),
        );

        $agendamento->load('profissional', 'servico', 'cliente');

        return AgendamentoResource::make($agendamento)
            ->response()
            ->setStatusCode(201);
    }
}

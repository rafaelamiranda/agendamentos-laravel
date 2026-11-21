<?php

namespace App\Http\Controllers\Api;

use App\Actions\GerarCobrancaPix;
use App\Actions\ReservarHorario;
use App\Enums\StatusAgendamento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CriarAgendamentoRequest;
use App\Http\Resources\AgendamentoResource;
use App\Pagamentos\FalhaGatewayPagamentoException;
use Illuminate\Http\JsonResponse;

class AgendamentoController extends Controller
{
    public function store(
        CriarAgendamentoRequest $request,
        ReservarHorario $reservar,
        GerarCobrancaPix $gerarCobranca,
    ): JsonResponse {
        $agendamento = $reservar->executar(
            $request->profissional(),
            $request->servico(),
            $request->inicio(),
            $request->dadosCliente(),
        );

        // O Pix é gerado depois do commit da reserva, nunca dentro da
        // transação: uma chamada HTTP de segundos segurando o lock do
        // profissional travaria todas as reservas dele nesse tempo.
        try {
            $gerarCobranca->executar($agendamento);
        } catch (FalhaGatewayPagamentoException $erro) {
            // Compensação: sem Pix não há como pagar, então a reserva é
            // desfeita na hora, em vez de segurar o horário até expirar.
            $agendamento->update([
                'status' => StatusAgendamento::Expirado,
                'expira_em' => now(),
            ]);

            report($erro);

            throw $erro;
        }

        $agendamento->load('profissional', 'servico', 'cliente');

        return AgendamentoResource::make($agendamento)
            ->response()
            ->setStatusCode(201);
    }
}

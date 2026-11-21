<?php

namespace App\Http\Controllers\Painel;

use App\Actions\CancelarAgendamento;
use App\Enums\OrigemCancelamento;
use App\Exceptions\AgendamentoNaoCancelavelException;
use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Cancelamento pelo estabelecimento: o sinal pago é sempre devolvido. */
class CancelamentoController extends Controller
{
    public function __invoke(Agendamento $agendamento, CancelarAgendamento $cancelar): RedirectResponse
    {
        Gate::authorize('update', $agendamento);

        try {
            $situacao = $cancelar->executar($agendamento, OrigemCancelamento::Estabelecimento);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Agendamento cancelado e cliente avisado por e-mail. '.$situacao->descricao(),
            ]);
        } catch (AgendamentoNaoCancelavelException $erro) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $erro->getMessage()]);
        }

        return back();
    }
}

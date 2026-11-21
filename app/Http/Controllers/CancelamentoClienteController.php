<?php

namespace App\Http\Controllers;

use App\Actions\CancelarAgendamento;
use App\Enums\OrigemCancelamento;
use App\Enums\SituacaoSinal;
use App\Enums\StatusAgendamento;
use App\Enums\StatusPagamento;
use App\Exceptions\AgendamentoNaoCancelavelException;
use App\Models\Agendamento;
use App\Support\Dinheiro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cancelamento pelo cliente, a partir do link assinado do e-mail.
 *
 * O GET só mostra a confirmação; quem cancela é o POST. Leitores de e-mail
 * e antivírus costumam abrir os links da mensagem sozinhos, e um GET que
 * cancelasse faria o cliente perder o horário sem clicar em nada.
 */
class CancelamentoClienteController extends Controller
{
    public function mostrar(Agendamento $agendamento, CancelarAgendamento $cancelar): Response
    {
        $agendamento->load('servico', 'profissional', 'pagamento');
        $sinalPago = $agendamento->pagamento?->status === StatusPagamento::Aprovado;

        return Inertia::render('publico/cancelamento', [
            'agendamento' => [
                'servico' => $agendamento->servico->nome,
                'profissional' => $agendamento->profissional->nome,
                'quando' => ucfirst($agendamento->inicio->translatedFormat('l, j \d\e F \d\e Y \à\s H:i')),
                'status' => ['valor' => $agendamento->status->value, 'rotulo' => $agendamento->status->rotulo()],
                'sinal' => 'R$ '.Dinheiro::formatar($agendamento->sinal_centavos),
            ],
            'pode_cancelar' => $agendamento->podeSerCancelado(),
            'aviso_sinal' => match (true) {
                ! $sinalPago => SituacaoSinal::SemPagamento->descricao(),
                $cancelar->devolveSinal($agendamento, OrigemCancelamento::Cliente) => SituacaoSinal::Devolvido->descricao(),
                default => SituacaoSinal::Retido->descricao(),
            },
            'situacao_sinal' => $agendamento->status === StatusAgendamento::Cancelado
                ? $this->situacaoAposCancelamento($agendamento)->descricao()
                : null,
            'prazo_horas' => (int) config('agendamentos.prazo_cancelamento_horas'),
            // O POST também precisa de URL assinada.
            'acao' => URL::temporarySignedRoute(
                'agendamentos.cancelamento.confirmar',
                $agendamento->inicio,
                ['agendamento' => $agendamento->id],
            ),
        ]);
    }

    public function confirmar(Agendamento $agendamento, CancelarAgendamento $cancelar): RedirectResponse
    {
        try {
            $cancelar->executar($agendamento, OrigemCancelamento::Cliente);
        } catch (AgendamentoNaoCancelavelException $erro) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $erro->getMessage()]);
        }

        return redirect()->to(URL::temporarySignedRoute(
            'agendamentos.cancelamento',
            $agendamento->inicio,
            ['agendamento' => $agendamento->id],
        ));
    }

    private function situacaoAposCancelamento(Agendamento $agendamento): SituacaoSinal
    {
        $pagamento = $agendamento->pagamento;

        return match (true) {
            $pagamento?->status === StatusPagamento::Estornado, $pagamento?->requer_estorno === true => SituacaoSinal::Devolvido,
            $pagamento?->status === StatusPagamento::Aprovado => SituacaoSinal::Retido,
            default => SituacaoSinal::SemPagamento,
        };
    }
}

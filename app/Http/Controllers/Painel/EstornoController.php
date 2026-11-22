<?php

namespace App\Http\Controllers\Painel;

use App\Enums\StatusPagamento;
use App\Http\Controllers\Controller;
use App\Jobs\EstornarPagamento;
use App\Models\Pagamento;
use App\Support\Dinheiro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sinais que precisam voltar para o cliente (requer_estorno). Na maioria
 * das vezes o job resolve sozinho em segundos; o que aparece aqui por mais
 * tempo é o que falhou nas tentativas automáticas.
 */
class EstornoController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Pagamento::class);

        $pendentes = Pagamento::query()
            ->where('requer_estorno', true)
            ->with('agendamento.cliente', 'agendamento.servico', 'agendamento.profissional')
            ->oldest('updated_at')
            ->get();

        return Inertia::render('estornos/index', [
            'estornos' => $pendentes->map(fn (Pagamento $pagamento) => [
                'id' => $pagamento->id,
                'valor' => 'R$ '.Dinheiro::formatar($pagamento->valor_centavos),
                'gateway_id' => $pagamento->gateway_id,
                'desde' => $pagamento->updated_at?->format('d/m/Y H:i'),
                'cliente' => [
                    'nome' => $pagamento->agendamento->cliente->nome,
                    'email' => $pagamento->agendamento->cliente->email,
                ],
                'servico' => $pagamento->agendamento->servico->nome,
                'profissional' => $pagamento->agendamento->profissional->nome,
                'horario' => $pagamento->agendamento->inicio->format('d/m/Y H:i'),
                'situacao' => $pagamento->agendamento->status->rotulo()
                    .($pagamento->agendamento->cancelado_por ? " pelo {$pagamento->agendamento->cancelado_por->value}" : ''),
            ]),
        ]);
    }

    /** Recoloca o estorno na fila (ex.: o Mercado Pago estava fora do ar). */
    public function tentarDeNovo(Pagamento $pagamento): RedirectResponse
    {
        Gate::authorize('update', $pagamento);

        if ($pagamento->requer_estorno) {
            EstornarPagamento::dispatch($pagamento->id);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Estorno enviado de novo para processamento.']);

        return back();
    }

    /**
     * O estabelecimento devolveu por fora (Pix manual, painel do Mercado
     * Pago). Tira a pendência e registra como estornado.
     */
    public function marcarComoDevolvido(Pagamento $pagamento): RedirectResponse
    {
        Gate::authorize('update', $pagamento);

        DB::transaction(function () use ($pagamento) {
            $pagamento = Pagamento::query()->lockForUpdate()->findOrFail($pagamento->id);

            $pagamento->update([
                'status' => $pagamento->status->podeMudarPara(StatusPagamento::Estornado)
                    ? StatusPagamento::Estornado
                    : $pagamento->status,
                'estornado_em' => $pagamento->estornado_em ?? now(),
                'requer_estorno' => false,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Estorno marcado como devolvido.']);

        return back();
    }
}

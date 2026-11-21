<?php

namespace App\Actions;

use App\Enums\OrigemCancelamento;
use App\Enums\SituacaoSinal;
use App\Enums\StatusAgendamento;
use App\Enums\StatusPagamento;
use App\Exceptions\AgendamentoNaoCancelavelException;
use App\Jobs\EstornarPagamento;
use App\Mail\AgendamentoCancelado;
use App\Models\Agendamento;
use App\Models\Pagamento;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Política de cancelamento:
 *  - o estabelecimento cancelando sempre devolve o sinal;
 *  - o cliente recebe o sinal de volta se cancelar com pelo menos
 *    `prazo_cancelamento_horas` de antecedência; depois disso ainda pode
 *    cancelar (o horário é liberado), mas o sinal fica retido.
 */
class CancelarAgendamento
{
    public function __construct(
        #[Config('agendamentos.prazo_cancelamento_horas')]
        private readonly int $prazoCancelamentoHoras,
    ) {}

    /** Usado também para avisar o cliente antes de ele confirmar. */
    public function devolveSinal(Agendamento $agendamento, OrigemCancelamento $origem): bool
    {
        return $origem === OrigemCancelamento::Estabelecimento
            || now()->addHours($this->prazoCancelamentoHoras) <= $agendamento->inicio;
    }

    /**
     * @throws AgendamentoNaoCancelavelException
     */
    public function executar(Agendamento $agendamento, OrigemCancelamento $origem): SituacaoSinal
    {
        return DB::transaction(function () use ($agendamento, $origem) {
            // Mesma ordem de locks do webhook (pagamento, depois agendamento):
            // um cancelamento e uma aprovação de pagamento ao mesmo tempo
            // passam um de cada vez, sem deadlock. Ordens diferentes poderiam
            // travar uma transação esperando a outra para sempre.
            $pagamento = Pagamento::query()->where('agendamento_id', $agendamento->id)->lockForUpdate()->first();
            $agendamento = Agendamento::query()->lockForUpdate()->findOrFail($agendamento->id);

            if (! $agendamento->podeSerCancelado()) {
                throw new AgendamentoNaoCancelavelException;
            }

            $agendamento->update([
                'status' => StatusAgendamento::Cancelado,
                'cancelado_em' => now(),
                'cancelado_por' => $origem,
            ]);

            $situacao = match (true) {
                $pagamento?->status !== StatusPagamento::Aprovado => SituacaoSinal::SemPagamento,
                $this->devolveSinal($agendamento, $origem) => SituacaoSinal::Devolvido,
                default => SituacaoSinal::Retido,
            };

            if ($situacao === SituacaoSinal::Devolvido) {
                // A flag fica até o estorno ser confirmado: se o job falhar
                // em todas as tentativas, o painel ainda mostra a pendência.
                $pagamento->update(['requer_estorno' => true]);

                // afterCommit: o job só entra na fila se o cancelamento for
                // gravado. Sem isso, um rollback deixaria um estorno de um
                // agendamento que continua ativo.
                EstornarPagamento::dispatch($pagamento->id)->afterCommit();
            }

            Mail::to($agendamento->cliente->email)->queue(new AgendamentoCancelado($agendamento, $situacao));

            return $situacao;
        });
    }
}

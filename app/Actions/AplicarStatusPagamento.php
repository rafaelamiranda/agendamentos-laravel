<?php

namespace App\Actions;

use App\Enums\StatusAgendamento;
use App\Enums\StatusPagamento;
use App\Jobs\EstornarPagamento;
use App\Mail\AgendamentoConfirmado;
use App\Models\Agendamento;
use App\Models\Pagamento;
use App\Models\Profissional;
use App\Pagamentos\PagamentoConsultado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Aplica ao pagamento e ao agendamento o estado informado pelo gateway.
 *
 * Idempotente: o Mercado Pago reenvia notificações e pode mandá-las fora de
 * ordem. Processar o mesmo aviso duas vezes, ou um "pendente" atrasado
 * depois de um "aprovado", não muda nada.
 */
class AplicarStatusPagamento
{
    public function executar(PagamentoConsultado $consultado): void
    {
        DB::transaction(function () use ($consultado) {
            // Lock no pagamento: dois avisos do mesmo pagamento processados
            // ao mesmo tempo (dois workers) passam um de cada vez, e o
            // segundo já vê o status gravado pelo primeiro.
            $pagamento = Pagamento::query()
                ->where('gateway_id', $consultado->gatewayId)
                ->lockForUpdate()
                ->first();

            if (! $pagamento) {
                Log::warning('Notificação de pagamento desconhecido.', ['gateway_id' => $consultado->gatewayId]);

                return;
            }

            if (! $pagamento->status->podeMudarPara($consultado->status)) {
                return;
            }

            $pagamento->status = $consultado->status;

            if ($consultado->status === StatusPagamento::Aprovado) {
                $pagamento->aprovado_em = $consultado->aprovadoEm ?? now();
                $this->confirmarAgendamento($pagamento, $consultado);
            }

            // Estorno feito (por nós ou direto no painel do Mercado Pago).
            if ($consultado->status === StatusPagamento::Estornado) {
                $pagamento->estornado_em = now();
                $pagamento->requer_estorno = false;
            }

            $pagamento->save();
        });
    }

    /**
     * Confirma o agendamento se ainda for possível; senão, marca o pagamento
     * para estorno. Quem pagou nunca fica sem horário e sem dinheiro.
     */
    private function confirmarAgendamento(Pagamento $pagamento, PagamentoConsultado $consultado): void
    {
        // Só leituras com lock até aqui: no REPEATABLE READ, a primeira
        // leitura comum define o snapshot da transação. Ela precisa vir
        // depois dos locks para enxergar a agenda atual (ver ReservarHorario).
        $agendamento = Agendamento::query()->whereKey($pagamento->agendamento_id)->lockForUpdate()->firstOrFail();

        if ($agendamento->status === StatusAgendamento::Confirmado) {
            return;
        }

        if ($consultado->valorCentavos !== $pagamento->valor_centavos) {
            $this->marcarParaEstorno($pagamento, $agendamento, 'valor pago diferente do sinal');

            return;
        }

        if (! in_array($agendamento->status, [StatusAgendamento::Pendente, StatusAgendamento::Expirado], true)) {
            $this->marcarParaEstorno($pagamento, $agendamento, "agendamento {$agendamento->status->value}");

            return;
        }

        // O pagamento pode chegar depois de a reserva vencer (fila atrasada,
        // job de expiração mais rápido). Se ninguém pegou o horário nesse
        // meio-tempo, confirma; se pegou, estorna. Mesmo lock da reserva,
        // para não disputar o horário com uma reserva nova.
        Profissional::query()->whereKey($agendamento->profissional_id)->lockForUpdate()->firstOrFail();

        $ocupado = Agendamento::query()
            ->where('profissional_id', $agendamento->profissional_id)
            ->whereKeyNot($agendamento->id)
            ->ocupandoHorario()
            ->sobrepondo($agendamento->inicio, $agendamento->fim)
            ->exists();

        if ($ocupado) {
            $this->marcarParaEstorno($pagamento, $agendamento, 'horário ocupado por outra reserva');

            return;
        }

        $agendamento->update(['status' => StatusAgendamento::Confirmado]);

        // Na fila e só após o commit (ver EmailDeAgendamento).
        Mail::to($agendamento->cliente->email)->queue(new AgendamentoConfirmado($agendamento));
    }

    private function marcarParaEstorno(Pagamento $pagamento, Agendamento $agendamento, string $motivo): void
    {
        $pagamento->requer_estorno = true;

        // Devolve sozinho; a flag só sai quando o estorno for confirmado.
        EstornarPagamento::dispatch($pagamento->id)->afterCommit();

        Log::warning('Pagamento aprovado sem confirmação do agendamento: estorno automático disparado.', [
            'pagamento_id' => $pagamento->id,
            'agendamento_id' => $agendamento->id,
            'motivo' => $motivo,
        ]);
    }
}

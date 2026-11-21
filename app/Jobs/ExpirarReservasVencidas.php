<?php

namespace App\Jobs;

use App\Enums\StatusAgendamento;
use App\Models\Agendamento;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Marca como expiradas as reservas pendentes cujo prazo do sinal passou.
 *
 * O horário já fica livre no instante em que expira_em passa (o scope
 * ocupandoHorario ignora pendentes vencidos). Este job só deixa o status
 * coerente para o painel e os relatórios, então um atraso dele não prende
 * horário de ninguém.
 */
class ExpirarReservasVencidas implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        // Um único UPDATE: atômico e sem carregar os models na memória.
        $expiradas = Agendamento::query()
            ->where('status', StatusAgendamento::Pendente)
            ->where('expira_em', '<=', now())
            ->update(['status' => StatusAgendamento::Expirado]);

        if ($expiradas > 0) {
            Log::info("Reservas expiradas: {$expiradas}.");
        }
    }
}

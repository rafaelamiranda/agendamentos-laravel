<?php

namespace App\Jobs;

use App\Enums\StatusAgendamento;
use App\Mail\LembreteAgendamento;
use App\Models\Agendamento;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Envia o lembrete para os confirmados que começam nas próximas N horas.
 *
 * Roda a cada 15 minutos e procura uma janela ("começa em até 24 h e ainda
 * não recebeu"), e não um instante exato ("começa daqui a 24 h"). Assim, se
 * o agendador ficar parado um tempo, os lembretes atrasados ainda saem
 * quando ele voltar.
 */
class EnviarLembretes implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $horasAntes = (int) config('agendamentos.lembrete_horas_antes');

        $candidatos = Agendamento::query()
            ->where('status', StatusAgendamento::Confirmado)
            ->whereNull('lembrete_enviado_em')
            ->where('inicio', '>', now())
            ->where('inicio', '<=', now()->addHours($horasAntes))
            ->with('cliente')
            ->get();

        foreach ($candidatos as $agendamento) {
            // Reservado já dentro da janela (ex.: hoje para amanhã cedo): o
            // e-mail de confirmação acabou de chegar, o lembrete seria ruído.
            if ($agendamento->created_at > $agendamento->inicio->subHours($horasAntes)) {
                continue;
            }

            // UPDATE condicional como "reserva" do envio: se duas execuções
            // pegarem o mesmo agendamento, só uma consegue marcar (1 linha
            // afetada) e só ela envia. Nunca sai lembrete em dobro.
            $marcou = Agendamento::query()
                ->whereKey($agendamento->id)
                ->whereNull('lembrete_enviado_em')
                ->update(['lembrete_enviado_em' => now()]);

            if ($marcou === 1) {
                Mail::to($agendamento->cliente->email)->queue(new LembreteAgendamento($agendamento));
            }
        }
    }
}

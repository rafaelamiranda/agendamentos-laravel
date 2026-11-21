<?php

namespace App\Mail;

use App\Models\Agendamento;
use App\Support\Dinheiro;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Base dos e-mails de agendamento: todos vão pela fila (o envio SMTP não
 * atrasa a requisição nem o webhook) e só depois do commit (um rollback não
 * pode deixar um e-mail de algo que não aconteceu).
 */
abstract class EmailDeAgendamento extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Agendamento $agendamento)
    {
        $this->afterCommit();
    }

    /** @return array<string, mixed> */
    protected function dadosComuns(): array
    {
        $agendamento = $this->agendamento->loadMissing('servico', 'profissional', 'cliente');

        return [
            'cliente' => $agendamento->cliente->nome,
            'servico' => $agendamento->servico->nome,
            'profissional' => $agendamento->profissional->nome,
            'quando' => $agendamento->inicio->translatedFormat('l, j \d\e F \d\e Y \à\s H:i'),
            'valor' => 'R$ '.Dinheiro::formatar($agendamento->valor_centavos),
            'sinal' => 'R$ '.Dinheiro::formatar($agendamento->sinal_centavos),
            'restante' => 'R$ '.Dinheiro::formatar($agendamento->valor_centavos - $agendamento->sinal_centavos),
            'prazoCancelamentoHoras' => (int) config('agendamentos.prazo_cancelamento_horas'),
        ];
    }

    /**
     * Link de cancelamento sem login: a assinatura impede trocar o id na
     * URL, e o link expira no horário do atendimento.
     */
    protected function linkCancelamento(): string
    {
        return URL::temporarySignedRoute(
            'agendamentos.cancelamento',
            $this->agendamento->inicio,
            ['agendamento' => $this->agendamento->id],
        );
    }
}

<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AgendamentoConfirmado extends EmailDeAgendamento
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Agendamento confirmado: '.$this->agendamento->inicio->format('d/m \à\s H:i'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.agendamentos.confirmado',
            with: [...$this->dadosComuns(), 'linkCancelamento' => $this->linkCancelamento()],
        );
    }
}

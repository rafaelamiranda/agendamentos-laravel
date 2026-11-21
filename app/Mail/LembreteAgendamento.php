<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LembreteAgendamento extends EmailDeAgendamento
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Lembrete: seu horário é '.$this->agendamento->inicio->translatedFormat('l \à\s H:i'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.agendamentos.lembrete',
            with: [...$this->dadosComuns(), 'linkCancelamento' => $this->linkCancelamento()],
        );
    }
}

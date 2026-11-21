<?php

namespace App\Mail;

use App\Enums\SituacaoSinal;
use App\Models\Agendamento;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AgendamentoCancelado extends EmailDeAgendamento
{
    public function __construct(Agendamento $agendamento, public SituacaoSinal $situacaoSinal)
    {
        parent::__construct($agendamento);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Agendamento cancelado: '.$this->agendamento->inicio->format('d/m \à\s H:i'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.agendamentos.cancelado',
            with: [
                ...$this->dadosComuns(),
                // Nome diferente da propriedade pública: o Mailable passa as
                // propriedades públicas para a view por cima do "with".
                'textoSinal' => $this->situacaoSinal->descricao(),
                'peloEstabelecimento' => $this->agendamento->cancelado_por?->value === 'estabelecimento',
            ],
        );
    }
}

<?php

namespace App\Enums;

enum StatusAgendamento: string
{
    /** Reservado, aguardando o pagamento do sinal até expira_em. */
    case Pendente = 'pendente';

    case Confirmado = 'confirmado';

    case Concluido = 'concluido';

    case Cancelado = 'cancelado';

    /** Pendente cujo sinal não foi pago a tempo; o horário volta a ficar livre. */
    case Expirado = 'expirado';

    public function rotulo(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Confirmado => 'Confirmado',
            self::Concluido => 'Concluído',
            self::Cancelado => 'Cancelado',
            self::Expirado => 'Expirado',
        };
    }

    /**
     * Indica se um agendamento neste status pode bloquear o horário do
     * profissional. Para o pendente isso vale só enquanto expira_em não
     * passou; essa parte é checada no scope Agendamento::ocupandoHorario().
     */
    public function podeOcuparHorario(): bool
    {
        return match ($this) {
            self::Pendente, self::Confirmado, self::Concluido => true,
            self::Cancelado, self::Expirado => false,
        };
    }
}

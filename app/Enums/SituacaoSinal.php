<?php

namespace App\Enums;

/** O que acontece com o sinal quando um agendamento é cancelado. */
enum SituacaoSinal: string
{
    case Devolvido = 'devolvido';

    case Retido = 'retido';

    /** Cancelado antes de o sinal ser pago: não há o que devolver. */
    case SemPagamento = 'sem_pagamento';

    public function descricao(): string
    {
        return match ($this) {
            self::Devolvido => 'O sinal será devolvido pelo mesmo meio de pagamento.',
            self::Retido => 'O cancelamento foi feito com menos antecedência que o prazo da política, então o sinal não é devolvido.',
            self::SemPagamento => 'O sinal não chegou a ser pago, então não há valor a devolver.',
        };
    }
}

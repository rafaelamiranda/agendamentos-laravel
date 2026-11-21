<?php

namespace App\Exceptions;

use RuntimeException;

/** Só pendentes e confirmados que ainda não começaram podem ser cancelados. */
class AgendamentoNaoCancelavelException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Este agendamento não pode mais ser cancelado.');
    }
}

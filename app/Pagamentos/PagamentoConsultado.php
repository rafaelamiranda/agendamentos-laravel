<?php

namespace App\Pagamentos;

use App\Enums\StatusPagamento;
use Carbon\CarbonImmutable;

final readonly class PagamentoConsultado
{
    public function __construct(
        public string $gatewayId,
        public StatusPagamento $status,
        public int $valorCentavos,
        public ?CarbonImmutable $aprovadoEm,
    ) {}
}

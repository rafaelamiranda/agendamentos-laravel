<?php

namespace App\Pagamentos;

use App\Enums\StatusPagamento;
use Carbon\CarbonImmutable;

final readonly class CobrancaPix
{
    public function __construct(
        public string $gatewayId,
        public StatusPagamento $status,
        public string $copiaECola,
        public string $qrCodeBase64,
        public CarbonImmutable $expiraEm,
    ) {}
}

<?php

namespace App\Http\Resources;

use App\Models\Pagamento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pagamento
 */
class PagamentoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'status' => [
                'valor' => $this->status->value,
                'rotulo' => $this->status->rotulo(),
            ],
            'valor_centavos' => $this->valor_centavos,
            'expira_em' => $this->expira_em->toIso8601String(),
            'pix' => [
                'copia_e_cola' => $this->pix_copia_e_cola,
                'qr_code_base64' => $this->pix_qr_code_base64,
            ],
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Agendamento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Agendamento
 */
class AgendamentoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => [
                'valor' => $this->status->value,
                'rotulo' => $this->status->rotulo(),
            ],
            // ISO 8601 com o deslocamento (-03:00), para não haver dúvida
            // sobre o fuso do lado de quem consome a API.
            'inicio' => $this->inicio->toIso8601String(),
            'fim' => $this->fim->toIso8601String(),
            'expira_em' => $this->expira_em?->toIso8601String(),
            'valor_centavos' => $this->valor_centavos,
            'sinal_centavos' => $this->sinal_centavos,
            'profissional' => [
                'id' => $this->profissional->id,
                'nome' => $this->profissional->nome,
            ],
            'servico' => [
                'id' => $this->servico->id,
                'nome' => $this->servico->nome,
                'duracao_minutos' => $this->servico->duracao_minutos,
            ],
            'cliente' => [
                'nome' => $this->cliente->nome,
                'email' => $this->cliente->email,
            ],
            'pagamento' => PagamentoResource::make($this->whenLoaded('pagamento')),
        ];
    }
}

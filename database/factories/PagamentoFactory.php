<?php

namespace Database\Factories;

use App\Enums\StatusPagamento;
use App\Models\Agendamento;
use App\Models\Pagamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pagamento>
 */
class PagamentoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agendamento_id' => Agendamento::factory()->pendente(),
            'gateway' => 'mercadopago',
            'gateway_id' => (string) fake()->unique()->numberBetween(100_000_000, 999_999_999),
            'status' => StatusPagamento::Pendente,
            'valor_centavos' => fn (array $atributos) => Agendamento::findOrFail($atributos['agendamento_id'])->sinal_centavos,
            'pix_copia_e_cola' => '00020126580014br.gov.bcb.pix0136'.fake()->uuid().'5204000053039865802BR6304ABCD',
            'pix_qr_code_base64' => base64_encode('qr-code-falso'),
            'expira_em' => now()->addMinutes(15),
        ];
    }

    public function aprovado(): static
    {
        return $this->state(fn () => [
            'status' => StatusPagamento::Aprovado,
            'aprovado_em' => now(),
        ]);
    }
}

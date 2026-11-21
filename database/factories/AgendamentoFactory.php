<?php

namespace Database\Factories;

use App\Enums\OrigemCancelamento;
use App\Enums\StatusAgendamento;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Profissional;
use App\Models\Servico;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Por padrão cria um agendamento confirmado no futuro. Fim, valor e sinal
 * são derivados do serviço, como acontece numa reserva real; basta passar
 * "inicio" (e opcionalmente o serviço) para ter um agendamento coerente.
 *
 * @extends Factory<Agendamento>
 */
class AgendamentoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'profissional_id' => Profissional::factory(),
            'servico_id' => Servico::factory(),
            'cliente_id' => Cliente::factory(),
            'inicio' => now()->addDays(fake()->numberBetween(1, 14))->setTime(fake()->numberBetween(9, 16), 0),
            'fim' => fn (array $atributos) => CarbonImmutable::parse($atributos['inicio'])
                ->addMinutes($this->servico($atributos)->duracao_minutos),
            'status' => StatusAgendamento::Confirmado,
            'valor_centavos' => fn (array $atributos) => $this->servico($atributos)->preco_centavos,
            'sinal_centavos' => fn (array $atributos) => $this->servico($atributos)->sinal_centavos,
            'expira_em' => null,
            'cancelado_em' => null,
        ];
    }

    public function confirmado(): static
    {
        return $this->state(fn () => [
            'status' => StatusAgendamento::Confirmado,
            'expira_em' => null,
        ]);
    }

    /** Aguardando o sinal, ainda dentro do prazo. */
    public function pendente(): static
    {
        return $this->state(fn () => [
            'status' => StatusAgendamento::Pendente,
            'expira_em' => now()->addMinutes(15),
        ]);
    }

    /** Ainda "pendente" no banco, mas com o prazo vencido (o job de expiração não rodou). */
    public function pendenteVencido(): static
    {
        return $this->state(fn () => [
            'status' => StatusAgendamento::Pendente,
            'expira_em' => now()->subMinute(),
        ]);
    }

    public function concluido(): static
    {
        return $this->state(fn () => [
            'status' => StatusAgendamento::Concluido,
            'inicio' => now()->subDays(fake()->numberBetween(1, 30))->setTime(fake()->numberBetween(9, 16), 0),
            'expira_em' => null,
        ]);
    }

    public function cancelado(): static
    {
        return $this->state(fn () => [
            'status' => StatusAgendamento::Cancelado,
            'cancelado_em' => now(),
            'cancelado_por' => OrigemCancelamento::Cliente,
        ]);
    }

    public function expirado(): static
    {
        return $this->state(fn () => [
            'status' => StatusAgendamento::Expirado,
            'expira_em' => now()->subMinutes(5),
        ]);
    }

    /** @param  array<string, mixed>  $atributos */
    private function servico(array $atributos): Servico
    {
        $servico = $atributos['servico_id'];

        return $servico instanceof Servico ? $servico : Servico::findOrFail($servico);
    }
}

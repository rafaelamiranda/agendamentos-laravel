<?php

namespace Database\Factories;

use App\Models\Servico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Servico>
 */
class ServicoFactory extends Factory
{
    public function definition(): array
    {
        // Múltiplo de R$ 5,00 entre R$ 40,00 e R$ 300,00.
        $preco = fake()->numberBetween(8, 60) * 500;

        return [
            'nome' => fake()->randomElement(['Corte', 'Escova', 'Hidratação', 'Manicure', 'Pedicure', 'Massagem']),
            'descricao' => fake()->sentence(),
            'duracao_minutos' => fake()->randomElement([30, 45, 60, 90]),
            'preco_centavos' => $preco,
            // Sinal de 30%, arredondado para centavos inteiros.
            'sinal_centavos' => intdiv($preco * 30, 100),
            'ativo' => true,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}

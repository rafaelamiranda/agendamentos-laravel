<?php

namespace Database\Factories;

use App\Models\Jornada;
use App\Models\Profissional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Jornada>
 */
class JornadaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'profissional_id' => Profissional::factory(),
            'dia_semana' => fake()->numberBetween(1, 5),
            'inicio' => '09:00:00',
            'fim' => '18:00:00',
        ];
    }
}

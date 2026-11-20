<?php

namespace Database\Factories;

use App\Models\Bloqueio;
use App\Models\Profissional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bloqueio>
 */
class BloqueioFactory extends Factory
{
    public function definition(): array
    {
        $inicio = now()->addDay()->setTime(12, 0);

        return [
            'profissional_id' => Profissional::factory(),
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addHour(),
            'motivo' => 'Almoço',
        ];
    }
}

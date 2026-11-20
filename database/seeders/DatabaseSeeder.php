<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name' => 'Administrador',
            'email' => 'admin@agendamentos.test',
        ]);

        $servicos = collect([
            ['nome' => 'Corte feminino', 'duracao_minutos' => 60, 'preco_centavos' => 12000, 'sinal_centavos' => 3000],
            ['nome' => 'Corte masculino', 'duracao_minutos' => 30, 'preco_centavos' => 6000, 'sinal_centavos' => 1500],
            ['nome' => 'Coloração', 'duracao_minutos' => 120, 'preco_centavos' => 25000, 'sinal_centavos' => 7500],
            ['nome' => 'Manicure', 'duracao_minutos' => 45, 'preco_centavos' => 5000, 'sinal_centavos' => 1500],
        ])->map(fn (array $dados) => Servico::factory()->create($dados));

        $profissionais = [
            ['nome' => 'Ana Souza', 'email' => 'ana@agendamentos.test', 'servicos' => [0, 2]],
            ['nome' => 'Bruno Lima', 'email' => 'bruno@agendamentos.test', 'servicos' => [0, 1]],
            ['nome' => 'Carla Mendes', 'email' => 'carla@agendamentos.test', 'servicos' => [3]],
        ];

        foreach ($profissionais as $dados) {
            $profissional = Profissional::factory()->create([
                'nome' => $dados['nome'],
                'email' => $dados['email'],
            ]);

            $profissional->servicos()->attach(
                $servicos->only($dados['servicos'])->pluck('id')
            );

            // Segunda a sexta, 9h às 18h.
            foreach (range(1, 5) as $diaSemana) {
                $profissional->jornadas()->create([
                    'dia_semana' => $diaSemana,
                    'inicio' => '09:00:00',
                    'fim' => '18:00:00',
                ]);
            }

            // Sábado, 9h às 13h.
            $profissional->jornadas()->create([
                'dia_semana' => 6,
                'inicio' => '09:00:00',
                'fim' => '13:00:00',
            ]);
        }

        Cliente::factory(10)->create();
    }
}

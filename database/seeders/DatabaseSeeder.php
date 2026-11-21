<?php

namespace Database\Seeders;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->administrador()->create([
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

        $clientes = Cliente::factory(10)->create();

        $this->agendaDeDemonstracao($clientes);
    }

    /**
     * Uma semana de agenda (da segunda passada até a próxima) para o painel
     * não abrir vazio: almoço bloqueado nos dias úteis e alguns atendimentos
     * por dia. Os do passado ficam concluídos; um de cada dia futuro fica
     * aguardando o sinal.
     *
     * @param  Collection<int, Cliente>  $clientes
     */
    private function agendaDeDemonstracao(Collection $clientes): void
    {
        $horarios = ['09:00', '10:30', '14:00', '15:30'];
        $inicioDoPeriodo = CarbonImmutable::today()->startOfWeek(CarbonImmutable::MONDAY);

        foreach (Profissional::with('servicos', 'jornadas')->get() as $profissional) {
            foreach (range(0, 13) as $deslocamento) {
                $dia = $inicioDoPeriodo->addDays($deslocamento);

                if ($profissional->jornadas->where('dia_semana', $dia->dayOfWeek)->isEmpty()) {
                    continue;
                }

                if (! $dia->isSaturday()) {
                    $profissional->bloqueios()->create([
                        'inicio' => $dia->setTime(12, 0),
                        'fim' => $dia->setTime(13, 0),
                        'motivo' => 'Almoço',
                    ]);
                }

                // Sábado só tem a manhã; nos outros dias, três dos quatro horários.
                $doDia = $dia->isSaturday()
                    ? ['09:00', '10:30']
                    : collect($horarios)->shuffle()->take(3)->sort()->values()->all();

                foreach ($doDia as $indice => $hora) {
                    $inicio = $dia->setTimeFromTimeString($hora);
                    $servico = $profissional->servicos->random();

                    // Serviços longos não podem invadir o almoço nem o fim do sábado.
                    if ($servico->duracao_minutos > 90) {
                        $servico = $profissional->servicos->sortBy('duracao_minutos')->first();
                    }

                    $estado = match (true) {
                        $inicio->isPast() => 'concluido',
                        $indice === 0 => 'pendente',
                        default => 'confirmado',
                    };

                    Agendamento::factory()->{$estado}()->create([
                        'profissional_id' => $profissional->id,
                        'servico_id' => $servico->id,
                        'cliente_id' => $clientes->random()->id,
                        'inicio' => $inicio,
                    ]);
                }
            }
        }
    }
}

<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Bloqueio;
use App\Models\Jornada;
use App\Models\Profissional;
use App\Models\Servico;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Calcula os horários em que um profissional pode atender um serviço numa
 * data.
 *
 * Um horário é livre quando o atendimento inteiro, [inicio, inicio + duração):
 *  - cabe numa jornada do profissional naquele dia da semana;
 *  - não cruza nenhum bloqueio;
 *  - não cruza nenhum agendamento que ocupa horário;
 *  - começa depois de agora + antecedência mínima;
 *  - cai num dia dentro da antecedência máxima (hoje + N dias, inclusive).
 *
 * O resultado é informativo. A garantia contra reserva dupla fica na
 * reserva, que confere o conflito de novo dentro de uma transação com lock.
 * Entre a consulta e a reserva, outro cliente pode ter pegado o horário.
 */
class CalculadoraDisponibilidade
{
    public function __construct(
        #[Config('agendamentos.antecedencia_minima_minutos')]
        private readonly int $antecedenciaMinimaMinutos,
        #[Config('agendamentos.intervalo_grade_minutos')]
        private readonly int $intervaloGradeMinutos,
        #[Config('agendamentos.antecedencia_maxima_dias')]
        private readonly int $antecedenciaMaximaDias,
    ) {
        // Com passo zero ou negativo, o laço da grade nunca terminaria.
        if ($intervaloGradeMinutos < 1) {
            throw new InvalidArgumentException('O intervalo da grade deve ser de pelo menos 1 minuto.');
        }
    }

    /**
     * @return Collection<int, CarbonImmutable> inícios dos horários livres, em ordem
     */
    public function horariosLivres(Profissional $profissional, Servico $servico, CarbonInterface|string $data): Collection
    {
        // Normaliza para o fuso do negócio: "23:30 UTC" é dia 30 às 20:30 em
        // Brasília, e é esse dia que define a jornada e a grade.
        $dia = CarbonImmutable::parse($data)->setTimezone(config('app.timezone'))->startOfDay();

        if ($dia > now()->startOfDay()->addDays($this->antecedenciaMaximaDias)) {
            return collect();
        }

        if (! $this->podeAtender($profissional, $servico)) {
            return collect();
        }

        $jornadas = $profissional->jornadas()
            ->where('dia_semana', $dia->dayOfWeek)
            ->orderBy('inicio')
            ->get();

        if ($jornadas->isEmpty()) {
            return collect();
        }

        // Carrega de uma vez tudo o que ocupa o dia e cruza em memória:
        // 2 consultas por dia, em vez de 2 por horário candidato.
        $fimDoDia = $dia->addDay();

        /** @var Collection<int, Bloqueio|Agendamento> $ocupacoes */
        $ocupacoes = $profissional->bloqueios()
            ->sobrepondo($dia, $fimDoDia)
            ->get()
            ->toBase()
            ->merge(
                $profissional->agendamentos()
                    ->ocupandoHorario()
                    ->sobrepondo($dia, $fimDoDia)
                    ->get()
            );

        $primeiroInicioPermitido = now()->addMinutes($this->antecedenciaMinimaMinutos);

        return $jornadas
            ->flatMap(fn (Jornada $jornada) => $this->candidatos($jornada, $dia, $servico->duracao_minutos))
            ->filter(fn (CarbonImmutable $inicio) => $inicio >= $primeiroInicioPermitido)
            ->reject(function (CarbonImmutable $inicio) use ($ocupacoes, $servico) {
                $fim = $inicio->addMinutes($servico->duracao_minutos);

                return $ocupacoes->contains(fn (Bloqueio|Agendamento $ocupacao) => $ocupacao->sobrepoe($inicio, $fim));
            })
            ->unique(fn (CarbonImmutable $inicio) => $inicio->getTimestamp())
            ->sort()
            ->values();
    }

    /**
     * Confere um horário específico usando a mesma regra da listagem. Assim,
     * um horário fora da grade (10:15 numa grade de 30 min) também é recusado.
     */
    public function estaLivre(Profissional $profissional, Servico $servico, CarbonInterface $inicio): bool
    {
        return $this->horariosLivres($profissional, $servico, $inicio)
            ->contains(fn (CarbonImmutable $livre) => $livre->equalTo($inicio));
    }

    private function podeAtender(Profissional $profissional, Servico $servico): bool
    {
        return $profissional->ativo
            && $servico->ativo
            && $profissional->servicos()->whereKey($servico->getKey())->exists();
    }

    /**
     * Inícios da grade dentro da jornada em que o atendimento termina até o
     * fim da jornada (terminar exatamente no fim é permitido).
     *
     * @return list<CarbonImmutable>
     */
    private function candidatos(Jornada $jornada, CarbonImmutable $dia, int $duracaoMinutos): array
    {
        $inicioJornada = $dia->setTimeFromTimeString($jornada->inicio);
        $fimJornada = $dia->setTimeFromTimeString($jornada->fim);

        $candidatos = [];

        for (
            $inicio = $inicioJornada;
            $inicio->addMinutes($duracaoMinutos) <= $fimJornada;
            $inicio = $inicio->addMinutes($this->intervaloGradeMinutos)
        ) {
            $candidatos[] = $inicio;
        }

        return $candidatos;
    }
}

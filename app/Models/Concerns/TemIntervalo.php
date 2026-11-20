<?php

namespace App\Models\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Para models com colunas "inicio" e "fim" (agendamentos e bloqueios).
 *
 * Os intervalos são semiabertos, [inicio, fim): o instante final não faz
 * parte do intervalo. Assim, um atendimento que termina às 11:00 não
 * conflita com outro que começa às 11:00, e horários encostados podem ser
 * oferecidos em sequência sem folga artificial de um minuto.
 */
trait TemIntervalo
{
    public function initializeTemIntervalo(): void
    {
        $this->mergeCasts([
            'inicio' => 'datetime',
            'fim' => 'datetime',
        ]);
    }

    /**
     * Registros que cruzam o intervalo [$inicio, $fim).
     *
     * Dois intervalos semiabertos A e B se sobrepõem quando
     * A.inicio < B.fim e A.fim > B.inicio. As comparações estritas
     * implementam a regra de "encostar não é conflito".
     */
    #[Scope]
    protected function sobrepondo(Builder $query, CarbonInterface $inicio, CarbonInterface $fim): void
    {
        $query->where($query->qualifyColumn('inicio'), '<', $fim)
            ->where($query->qualifyColumn('fim'), '>', $inicio);
    }
}

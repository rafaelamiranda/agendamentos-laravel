<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Models\Profissional;
use App\Services\MontarAgenda;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AgendaController extends Controller
{
    public function __invoke(Request $request, MontarAgenda $montar): Response
    {
        Gate::authorize('viewAny', Agendamento::class);

        $validado = $request->validate([
            'data' => ['nullable', 'date_format:Y-m-d'],
            'visao' => ['nullable', 'in:dia,semana'],
            'profissional' => ['nullable', 'integer', 'exists:profissionais,id'],
        ]);

        $data = isset($validado['data']) ? CarbonImmutable::parse($validado['data']) : CarbonImmutable::today();
        $visao = $validado['visao'] ?? 'dia';

        $profissionais = Profissional::query()->where('ativo', true)->orderBy('nome')->get(['id', 'nome']);

        // Semana é sempre de um profissional; sem escolha, usa o primeiro.
        $profissional = isset($validado['profissional'])
            ? Profissional::find($validado['profissional'])
            : $profissionais->first();

        $agenda = $visao === 'semana' && $profissional
            ? $montar->semana($profissional, $data)
            : $montar->dia($data);

        $passo = $visao === 'semana' ? 7 : 1;
        $segunda = $data->startOfWeek(CarbonImmutable::MONDAY);

        return Inertia::render('agenda/index', [
            ...$agenda,
            'visao' => $visao,
            'data' => $data->toDateString(),
            'titulo' => $visao === 'semana'
                ? "{$segunda->format('d/m')} a {$segunda->addDays(6)->format('d/m/Y')}"
                : ucfirst($data->translatedFormat('l, j \d\e F \d\e Y')),
            'anterior' => $data->subDays($passo)->toDateString(),
            'proxima' => $data->addDays($passo)->toDateString(),
            'hoje' => CarbonImmutable::today()->toDateString(),
            'profissionais' => $profissionais,
            'profissional_id' => $profissional?->id,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Painel\ProfissionalRequest;
use App\Models\Bloqueio;
use App\Models\Jornada;
use App\Models\Profissional;
use App\Models\Servico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProfissionalController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Profissional::class);

        return Inertia::render('profissionais/index', [
            'profissionais' => Profissional::query()
                ->withCount('servicos')
                ->orderByDesc('ativo')
                ->orderBy('nome')
                ->get()
                ->map(fn (Profissional $profissional) => [
                    'id' => $profissional->id,
                    'nome' => $profissional->nome,
                    'email' => $profissional->email,
                    'ativo' => $profissional->ativo,
                    'servicos_count' => $profissional->servicos_count,
                ]),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Profissional::class);

        return Inertia::render('profissionais/form', [
            'profissional' => null,
            'servicos' => $this->servicosDisponiveis(),
        ]);
    }

    public function store(ProfissionalRequest $request): RedirectResponse
    {
        $profissional = Profissional::create($request->safe()->except('servicos'));
        $profissional->servicos()->sync($request->validated('servicos'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional cadastrado. Agora defina as jornadas.']);

        return to_route('profissionais.edit', $profissional);
    }

    public function edit(Profissional $profissional): Response
    {
        Gate::authorize('update', $profissional);

        return Inertia::render('profissionais/form', [
            'profissional' => [
                'id' => $profissional->id,
                'nome' => $profissional->nome,
                'email' => $profissional->email,
                'ativo' => $profissional->ativo,
                'servicos' => $profissional->servicos()->pluck('servicos.id'),
                'jornadas' => $profissional->jornadas()
                    ->orderBy('dia_semana')
                    ->orderBy('inicio')
                    ->get()
                    ->map(fn (Jornada $jornada) => [
                        'id' => $jornada->id,
                        'dia_semana' => $jornada->dia_semana,
                        'inicio' => substr($jornada->inicio, 0, 5),
                        'fim' => substr($jornada->fim, 0, 5),
                    ]),
                // Só os bloqueios que ainda não terminaram: o histórico não
                // precisa ocupar a tela.
                'bloqueios' => $profissional->bloqueios()
                    ->where('fim', '>', now())
                    ->orderBy('inicio')
                    ->get()
                    ->map(fn (Bloqueio $bloqueio) => [
                        'id' => $bloqueio->id,
                        'inicio' => $bloqueio->inicio->format('Y-m-d\TH:i'),
                        'fim' => $bloqueio->fim->format('Y-m-d\TH:i'),
                        'motivo' => $bloqueio->motivo,
                    ]),
            ],
            'servicos' => $this->servicosDisponiveis(),
        ]);
    }

    public function update(ProfissionalRequest $request, Profissional $profissional): RedirectResponse
    {
        $profissional->update($request->safe()->except('servicos'));
        $profissional->servicos()->sync($request->validated('servicos'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional atualizado.']);

        return to_route('profissionais.edit', $profissional);
    }

    public function destroy(Profissional $profissional): RedirectResponse
    {
        Gate::authorize('delete', $profissional);

        // O banco também recusaria (restrictOnDelete), mas aqui a pessoa
        // recebe uma explicação em vez de um erro 500.
        if ($profissional->agendamentos()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Este profissional tem agendamentos e não pode ser excluído. Desative o cadastro.',
            ]);

            return back();
        }

        $profissional->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional excluído.']);

        return to_route('profissionais.index');
    }

    /** @return list<array{id: int, nome: string, ativo: bool}> */
    private function servicosDisponiveis(): array
    {
        return Servico::query()
            ->orderBy('nome')
            ->get(['id', 'nome', 'ativo'])
            ->map(fn (Servico $servico) => ['id' => $servico->id, 'nome' => $servico->nome, 'ativo' => $servico->ativo])
            ->all();
    }
}

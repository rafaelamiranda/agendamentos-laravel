<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Painel\ServicoRequest;
use App\Models\Servico;
use App\Support\Dinheiro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ServicoController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Servico::class);

        return Inertia::render('servicos/index', [
            'servicos' => Servico::query()
                ->withCount('profissionais')
                ->orderByDesc('ativo')
                ->orderBy('nome')
                ->get()
                ->map(fn (Servico $servico) => [
                    'id' => $servico->id,
                    'nome' => $servico->nome,
                    'duracao_minutos' => $servico->duracao_minutos,
                    'preco' => Dinheiro::formatar($servico->preco_centavos),
                    'sinal' => Dinheiro::formatar($servico->sinal_centavos),
                    'ativo' => $servico->ativo,
                    'profissionais_count' => $servico->profissionais_count,
                ]),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Servico::class);

        return Inertia::render('servicos/form', ['servico' => null]);
    }

    public function store(ServicoRequest $request): RedirectResponse
    {
        Servico::create($request->dados());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Serviço cadastrado.']);

        return to_route('servicos.index');
    }

    public function edit(Servico $servico): Response
    {
        Gate::authorize('update', $servico);

        return Inertia::render('servicos/form', [
            'servico' => [
                'id' => $servico->id,
                'nome' => $servico->nome,
                'descricao' => $servico->descricao,
                'duracao_minutos' => $servico->duracao_minutos,
                'preco' => Dinheiro::formatar($servico->preco_centavos),
                'sinal' => Dinheiro::formatar($servico->sinal_centavos),
                'ativo' => $servico->ativo,
            ],
        ]);
    }

    public function update(ServicoRequest $request, Servico $servico): RedirectResponse
    {
        // Agendamentos já feitos guardam o preço da época; mudar aqui só
        // vale para as próximas reservas.
        $servico->update($request->dados());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Serviço atualizado.']);

        return to_route('servicos.index');
    }

    public function destroy(Servico $servico): RedirectResponse
    {
        Gate::authorize('delete', $servico);

        if ($servico->agendamentos()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Este serviço tem agendamentos e não pode ser excluído. Desative o cadastro.',
            ]);

            return back();
        }

        $servico->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Serviço excluído.']);

        return to_route('servicos.index');
    }
}

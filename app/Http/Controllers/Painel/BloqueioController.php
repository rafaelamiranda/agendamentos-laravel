<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Painel\BloqueioRequest;
use App\Models\Bloqueio;
use App\Models\Profissional;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class BloqueioController extends Controller
{
    public function store(BloqueioRequest $request, Profissional $profissional): RedirectResponse
    {
        $profissional->bloqueios()->create([
            'inicio' => $request->inicio(),
            'fim' => $request->fim(),
            'motivo' => $request->validated('motivo'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bloqueio adicionado.']);

        return to_route('profissionais.edit', $profissional);
    }

    public function update(BloqueioRequest $request, Profissional $profissional, Bloqueio $bloqueio): RedirectResponse
    {
        $bloqueio->update([
            'inicio' => $request->inicio(),
            'fim' => $request->fim(),
            'motivo' => $request->validated('motivo'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bloqueio atualizado.']);

        return to_route('profissionais.edit', $profissional);
    }

    public function destroy(Profissional $profissional, Bloqueio $bloqueio): RedirectResponse
    {
        Gate::authorize('delete', $bloqueio);

        $bloqueio->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bloqueio removido.']);

        return to_route('profissionais.edit', $profissional);
    }
}

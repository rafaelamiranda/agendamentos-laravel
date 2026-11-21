<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Painel\JornadaRequest;
use App\Models\Jornada;
use App\Models\Profissional;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Rotas aninhadas e com escopo (/profissionais/{profissional}/jornadas/{jornada}):
 * uma jornada de outro profissional na URL dá 404, não edição indevida.
 */
class JornadaController extends Controller
{
    public function store(JornadaRequest $request, Profissional $profissional): RedirectResponse
    {
        $profissional->jornadas()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jornada adicionada.']);

        return to_route('profissionais.edit', $profissional);
    }

    public function update(JornadaRequest $request, Profissional $profissional, Jornada $jornada): RedirectResponse
    {
        $jornada->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jornada atualizada.']);

        return to_route('profissionais.edit', $profissional);
    }

    public function destroy(Profissional $profissional, Jornada $jornada): RedirectResponse
    {
        Gate::authorize('delete', $jornada);

        $jornada->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jornada removida.']);

        return to_route('profissionais.edit', $profissional);
    }
}

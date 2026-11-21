<?php

use App\Http\Controllers\Painel\AgendaController;
use App\Http\Controllers\Painel\BloqueioController;
use App\Http\Controllers\Painel\JornadaController;
use App\Http\Controllers\Painel\ProfissionalController;
use App\Http\Controllers\Painel\ServicoController;
use Illuminate\Support\Facades\Route;

// O sistema não tem página pública: o cliente usa a API, e a raiz leva
// direto para o painel (o login, para quem não está autenticado).
Route::redirect('/', '/agenda')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Mantido pelo starter kit (Fortify redireciona para cá após o login).
    Route::redirect('dashboard', '/agenda')->name('dashboard');

    Route::get('agenda', AgendaController::class)->name('agenda');

    Route::resource('profissionais', ProfissionalController::class)
        ->parameters(['profissionais' => 'profissional'])
        ->except('show');

    Route::resource('servicos', ServicoController::class)
        ->parameters(['servicos' => 'servico'])
        ->except('show');

    // scoped(): {jornada} e {bloqueio} são buscados entre os do
    // {profissional} da URL.
    Route::resource('profissionais.jornadas', JornadaController::class)
        ->parameters(['profissionais' => 'profissional', 'jornadas' => 'jornada'])
        ->only(['store', 'update', 'destroy'])
        ->scoped();

    Route::resource('profissionais.bloqueios', BloqueioController::class)
        ->parameters(['profissionais' => 'profissional', 'bloqueios' => 'bloqueio'])
        ->only(['store', 'update', 'destroy'])
        ->scoped();
});

require __DIR__.'/settings.php';

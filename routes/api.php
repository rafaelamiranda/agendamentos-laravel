<?php

use App\Http\Controllers\Api\AgendamentoController;
use App\Http\Controllers\Api\DisponibilidadeController;
use Illuminate\Support\Facades\Route;

Route::get('profissionais/{profissional}/disponibilidade', DisponibilidadeController::class)
    ->name('api.disponibilidade');

// Rota pública: o limite por IP evita que alguém segure a agenda inteira
// com reservas pendentes que nunca serão pagas.
Route::post('agendamentos', [AgendamentoController::class, 'store'])
    ->middleware('throttle:reservas')
    ->name('api.agendamentos.store');

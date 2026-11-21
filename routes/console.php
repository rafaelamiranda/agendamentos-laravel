<?php

use App\Jobs\EnviarLembretes;
use App\Jobs\ExpirarReservasVencidas;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Deixa o status das reservas vencidas coerente. O horário em si já fica
// livre quando expira_em passa (ver Agendamento::ocupandoHorario).
Schedule::job(new ExpirarReservasVencidas)
    ->everyMinute()
    ->withoutOverlapping();

Schedule::job(new EnviarLembretes)
    ->everyFifteenMinutes()
    ->withoutOverlapping();

<?php

use App\Enums\StatusAgendamento;
use App\Jobs\ExpirarReservasVencidas;
use App\Models\Agendamento;
use Illuminate\Console\Scheduling\Schedule;

test('expira só as reservas pendentes com prazo vencido', function () {
    $this->freezeTime();

    $vencida = Agendamento::factory()->pendenteVencido()->create();
    $venceAgora = Agendamento::factory()->pendente()->create(['expira_em' => now()]);
    $noPrazo = Agendamento::factory()->pendente()->create();
    $confirmado = Agendamento::factory()->confirmado()->create(['expira_em' => now()->subHour()]);
    $cancelado = Agendamento::factory()->cancelado()->create(['expira_em' => now()->subHour()]);

    (new ExpirarReservasVencidas)->handle();

    expect($vencida->fresh()->status)->toBe(StatusAgendamento::Expirado)
        ->and($venceAgora->fresh()->status)->toBe(StatusAgendamento::Expirado)
        ->and($noPrazo->fresh()->status)->toBe(StatusAgendamento::Pendente)
        ->and($confirmado->fresh()->status)->toBe(StatusAgendamento::Confirmado)
        ->and($cancelado->fresh()->status)->toBe(StatusAgendamento::Cancelado);
});

test('está agendado para rodar a cada minuto', function () {
    $evento = collect(app(Schedule::class)->events())
        ->first(fn ($evento) => str_contains((string) $evento->description, ExpirarReservasVencidas::class));

    expect($evento)->not->toBeNull()
        ->and($evento->expression)->toBe('* * * * *')
        ->and($evento->withoutOverlapping)->toBeTrue();
});

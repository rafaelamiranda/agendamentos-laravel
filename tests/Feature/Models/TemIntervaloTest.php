<?php

use App\Models\Agendamento;
use App\Models\Bloqueio;
use Carbon\CarbonImmutable;

// Registro existente em todos os casos: 10:00 às 11:00.
beforeEach(function () {
    $this->bloqueio = Bloqueio::factory()->create([
        'inicio' => CarbonImmutable::parse('2026-12-01 10:00'),
        'fim' => CarbonImmutable::parse('2026-12-01 11:00'),
    ]);
});

test('encontra intervalos que se sobrepõem', function (string $inicio, string $fim) {
    $encontrados = Bloqueio::sobrepondo(
        CarbonImmutable::parse("2026-12-01 {$inicio}"),
        CarbonImmutable::parse("2026-12-01 {$fim}"),
    )->get();

    expect($encontrados)->toHaveCount(1)
        ->and($encontrados->first()->is($this->bloqueio))->toBeTrue();
})->with([
    'idêntico' => ['10:00', '11:00'],
    'começa antes e termina no meio' => ['09:30', '10:30'],
    'começa no meio e termina depois' => ['10:30', '11:30'],
    'contido no existente' => ['10:15', '10:45'],
    'contém o existente' => ['09:00', '12:00'],
    'mesmo início, termina antes' => ['10:00', '10:30'],
    'começa no meio, mesmo fim' => ['10:30', '11:00'],
]);

test('não encontra intervalos que só encostam ou estão fora', function (string $inicio, string $fim) {
    $encontrados = Bloqueio::sobrepondo(
        CarbonImmutable::parse("2026-12-01 {$inicio}"),
        CarbonImmutable::parse("2026-12-01 {$fim}"),
    )->get();

    expect($encontrados)->toBeEmpty();
})->with([
    'termina exatamente no início do existente' => ['09:00', '10:00'],
    'começa exatamente no fim do existente' => ['11:00', '12:00'],
    'totalmente antes' => ['08:00', '09:00'],
    'totalmente depois' => ['12:00', '13:00'],
]);

test('o mesmo scope funciona em agendamentos', function () {
    $agendamento = Agendamento::factory()->create([
        'inicio' => CarbonImmutable::parse('2026-12-01 14:00'),
        'fim' => CarbonImmutable::parse('2026-12-01 15:00'),
    ]);

    expect(Agendamento::sobrepondo(CarbonImmutable::parse('2026-12-01 14:30'), CarbonImmutable::parse('2026-12-01 15:30'))->pluck('id'))
        ->toEqual(collect([$agendamento->id]))
        ->and(Agendamento::sobrepondo(CarbonImmutable::parse('2026-12-01 15:00'), CarbonImmutable::parse('2026-12-01 16:00'))->exists())
        ->toBeFalse();
});

test('inicio e fim são convertidos para datas imutáveis', function () {
    expect($this->bloqueio->fresh()->inicio)->toBeInstanceOf(CarbonImmutable::class)
        ->and($this->bloqueio->fresh()->fim->format('H:i'))->toBe('11:00');
});

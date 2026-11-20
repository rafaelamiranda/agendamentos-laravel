<?php

use App\Enums\StatusAgendamento;
use App\Models\Agendamento;
use App\Models\Servico;

beforeEach(function () {
    $this->freezeTime();
});

test('ocupandoHorario considera confirmados, concluídos e pendentes dentro do prazo', function () {
    $confirmado = Agendamento::factory()->confirmado()->create();
    $concluido = Agendamento::factory()->concluido()->create();
    $pendente = Agendamento::factory()->pendente()->create();

    Agendamento::factory()->pendenteVencido()->create();
    Agendamento::factory()->cancelado()->create();
    Agendamento::factory()->expirado()->create();

    expect(Agendamento::ocupandoHorario()->pluck('id')->sort()->values())
        ->toEqual(collect([$confirmado->id, $concluido->id, $pendente->id])->sort()->values());
});

test('pendente deixa de ocupar o horário no instante em que expira', function () {
    $pendente = Agendamento::factory()->pendente()->create([
        'expira_em' => now()->addMinutes(10),
    ]);

    expect(Agendamento::ocupandoHorario()->whereKey($pendente->id)->exists())->toBeTrue();

    $this->travel(10)->minutes();

    expect(Agendamento::ocupandoHorario()->whereKey($pendente->id)->exists())->toBeFalse();
});

test('ocupandoHorario combina com sobrepondo para achar conflitos', function () {
    $existente = Agendamento::factory()->confirmado()->create([
        'inicio' => now()->addDay()->setTime(10, 0),
        'fim' => now()->addDay()->setTime(11, 0),
    ]);
    Agendamento::factory()->cancelado()->create([
        'profissional_id' => $existente->profissional_id,
        'inicio' => now()->addDay()->setTime(10, 0),
        'fim' => now()->addDay()->setTime(11, 0),
    ]);

    $conflitos = $existente->profissional->agendamentos()
        ->ocupandoHorario()
        ->sobrepondo(now()->addDay()->setTime(10, 30), now()->addDay()->setTime(11, 30))
        ->get();

    expect($conflitos)->toHaveCount(1)
        ->and($conflitos->first()->is($existente))->toBeTrue();
});

test('status é convertido para o enum', function () {
    $agendamento = Agendamento::factory()->pendente()->create();

    expect($agendamento->fresh()->status)->toBe(StatusAgendamento::Pendente);
});

test('a factory copia duração, preço e sinal do serviço', function () {
    $servico = Servico::factory()->create([
        'duracao_minutos' => 45,
        'preco_centavos' => 12000,
        'sinal_centavos' => 3000,
    ]);

    $agendamento = Agendamento::factory()->for($servico)->create([
        'inicio' => now()->addDay()->setTime(9, 0),
    ]);

    expect($agendamento->fim->format('H:i'))->toBe('09:45')
        ->and($agendamento->valor_centavos)->toBe(12000)
        ->and($agendamento->sinal_centavos)->toBe(3000);
});

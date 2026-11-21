<?php

use App\Models\Jornada;
use App\Models\Profissional;
use App\Models\Servico;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 08:00'));

    // Cada reserva criada gera um Pix: o Mercado Pago é simulado.
    Http::fake(['api.mercadopago.com/v1/payments' => fn () => Http::response(
        respostaPagamentoMercadoPago(['id' => fake()->unique()->numberBetween(1, 999_999_999)]),
        201,
    )]);

    config([
        'agendamentos.antecedencia_minima_minutos' => 120,
        'agendamentos.antecedencia_maxima_dias' => 60,
        'agendamentos.intervalo_grade_minutos' => 30,
    ]);

    $this->servico = Servico::factory()->create(['duracao_minutos' => 60]);
    $this->profissional = Profissional::factory()->hasAttached($this->servico)->create();
    Jornada::factory()->for($this->profissional)->create(['dia_semana' => 1, 'inicio' => '09:00:00', 'fim' => '12:00:00']);

    $this->url = fn (array $query = []) => route('api.disponibilidade', [
        'profissional' => $this->profissional,
        'servico_id' => $this->servico->id,
        'data' => '2026-11-30',
        ...$query,
    ]);
});

test('lista os horários livres com início e fim em ISO 8601', function () {
    $this->getJson(($this->url)())
        ->assertOk()
        ->assertExactJson(['data' => [
            ['inicio' => '2026-11-30T09:00:00-03:00', 'fim' => '2026-11-30T10:00:00-03:00'],
            ['inicio' => '2026-11-30T09:30:00-03:00', 'fim' => '2026-11-30T10:30:00-03:00'],
            ['inicio' => '2026-11-30T10:00:00-03:00', 'fim' => '2026-11-30T11:00:00-03:00'],
            ['inicio' => '2026-11-30T10:30:00-03:00', 'fim' => '2026-11-30T11:30:00-03:00'],
            ['inicio' => '2026-11-30T11:00:00-03:00', 'fim' => '2026-11-30T12:00:00-03:00'],
        ]]);
});

test('horário reservado deixa de aparecer na disponibilidade', function () {
    $this->postJson(route('api.agendamentos.store'), [
        'profissional_id' => $this->profissional->id,
        'servico_id' => $this->servico->id,
        'inicio' => '2026-11-30T10:00:00-03:00',
        'cliente' => ['nome' => 'Maria', 'email' => 'maria@example.com', 'telefone' => '(11) 91234-5678'],
    ])->assertCreated();

    $inicios = collect($this->getJson(($this->url)())->json('data'))->pluck('inicio');

    expect($inicios->all())->toBe(['2026-11-30T09:00:00-03:00', '2026-11-30T11:00:00-03:00']);
});

test('valida serviço e data', function () {
    $this->getJson(($this->url)(['servico_id' => null, 'data' => '30/11/2026']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'servico_id' => 'O campo serviço é obrigatório.',
            'data' => 'O campo data deve estar no formato Y-m-d.',
        ]);
});

test('responde 404 para profissional inexistente', function () {
    $this->getJson(route('api.disponibilidade', [
        'profissional' => 999,
        'servico_id' => $this->servico->id,
        'data' => '2026-11-30',
    ]))->assertNotFound();
});

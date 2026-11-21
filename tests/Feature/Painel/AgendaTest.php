<?php

use App\Models\Agendamento;
use App\Models\Bloqueio;
use App\Models\Jornada;
use App\Models\Profissional;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-30 08:00'));
    $this->actingAs(User::factory()->administrador()->create());

    $this->ana = Profissional::factory()->create(['nome' => 'Ana']);
    $this->bruno = Profissional::factory()->create(['nome' => 'Bruno']);
    Profissional::factory()->inativo()->create(['nome' => 'Inativo']);

    foreach ([$this->ana, $this->bruno] as $profissional) {
        foreach (range(1, 5) as $dia) {
            Jornada::factory()->for($profissional)->create(['dia_semana' => $dia, 'inicio' => '09:00:00', 'fim' => '18:00:00']);
        }
    }
});

test('visão dia: uma coluna por profissional ativo, com jornada, bloqueios e agendamentos', function () {
    Agendamento::factory()->for($this->ana)->confirmado()->create([
        'inicio' => CarbonImmutable::parse('2026-11-30 10:00'),
        'fim' => CarbonImmutable::parse('2026-11-30 11:00'),
    ]);
    Bloqueio::factory()->for($this->ana)->create([
        'inicio' => CarbonImmutable::parse('2026-11-30 12:00'),
        'fim' => CarbonImmutable::parse('2026-11-30 13:00'),
        'motivo' => 'Almoço',
    ]);

    $this->get(route('agenda', ['data' => '2026-11-30']))
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('agenda/index')
            ->where('visao', 'dia')
            ->where('titulo', 'Segunda-feira, 30 de novembro de 2026')
            ->where('anterior', '2026-11-29')
            ->where('proxima', '2026-12-01')
            ->where('hora_inicial', 8)
            ->where('hora_final', 18)
            ->has('colunas', 2)
            ->where('colunas.0.titulo', 'Ana')
            ->where('colunas.0.jornadas', [['inicio' => '09:00', 'fim' => '18:00']])
            ->where('colunas.0.bloqueios.0.motivo', 'Almoço')
            ->where('colunas.0.agendamentos.0.inicio', '10:00')
            ->where('colunas.0.agendamentos.0.fim', '11:00')
            ->where('colunas.0.agendamentos.0.status.valor', 'confirmado')
            ->where('colunas.1.titulo', 'Bruno')
            ->has('colunas.1.agendamentos', 0));
});

test('só aparecem agendamentos que ocupam horário', function () {
    foreach (['confirmado', 'pendente', 'pendenteVencido', 'cancelado', 'expirado'] as $indice => $estado) {
        Agendamento::factory()->for($this->ana)->{$estado}()->create([
            'inicio' => CarbonImmutable::parse('2026-11-30 09:00')->addHours($indice),
        ]);
    }

    $this->get(route('agenda', ['data' => '2026-11-30']))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->has('colunas.0.agendamentos', 2)
            ->where('colunas.0.agendamentos.0.status.valor', 'confirmado')
            ->where('colunas.0.agendamentos.1.status.valor', 'pendente'));
});

test('visão semana: segunda a domingo de um profissional, com navegação de 7 dias', function () {
    Agendamento::factory()->for($this->bruno)->confirmado()->create([
        'inicio' => CarbonImmutable::parse('2026-12-03 14:00'),
    ]);

    $this->get(route('agenda', ['data' => '2026-12-02', 'visao' => 'semana', 'profissional' => $this->bruno->id]))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('visao', 'semana')
            ->where('titulo', '30/11 a 06/12/2026')
            ->where('anterior', '2026-11-25')
            ->where('proxima', '2026-12-09')
            ->where('profissional_id', $this->bruno->id)
            ->has('colunas', 7)
            ->where('colunas.0.titulo', 'Segunda-feira')
            ->where('colunas.0.subtitulo', '30/11')
            ->where('colunas.6.titulo', 'Domingo')
            ->has('colunas.6.jornadas', 0)
            ->where('colunas.3.agendamentos.0.inicio', '14:00'));
});

test('bloqueio de vários dias aparece recortado em cada dia', function () {
    Bloqueio::factory()->for($this->ana)->create([
        'inicio' => CarbonImmutable::parse('2026-11-29 18:00'),
        'fim' => CarbonImmutable::parse('2026-12-01 10:00'),
        'motivo' => 'Férias',
    ]);

    $this->get(route('agenda', ['data' => '2026-11-30', 'visao' => 'semana', 'profissional' => $this->ana->id]))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('colunas.0.bloqueios.0.inicio', '00:00')
            ->where('colunas.0.bloqueios.0.fim', '24:00')
            ->where('colunas.1.bloqueios.0.inicio', '00:00')
            ->where('colunas.1.bloqueios.0.fim', '10:00')
            ->has('colunas.2.bloqueios', 0));
});

test('sem data, mostra hoje', function () {
    $this->get(route('agenda'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('data', '2026-11-30')
            ->where('hoje', '2026-11-30'));
});

test('rejeita parâmetros inválidos', function () {
    $this->get(route('agenda', ['data' => '30/11/2026', 'visao' => 'mes']))
        ->assertSessionHasErrors(['data', 'visao']);
});

test('o número de consultas não cresce com a quantidade de agendamentos', function () {
    $contar = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('agenda', ['data' => '2026-11-30']))->assertOk();

        return count(DB::getQueryLog());
    };

    // Com um agendamento, o eager loading (cliente e serviço) já roda; daí
    // em diante o número de consultas não pode depender da quantidade.
    Agendamento::factory()->for($this->ana)->confirmado()->create([
        'inicio' => CarbonImmutable::parse('2026-11-30 09:00'),
    ]);
    $comUm = $contar();

    foreach (range(0, 7) as $hora) {
        Agendamento::factory()->for($this->bruno)->confirmado()->create([
            'inicio' => CarbonImmutable::parse('2026-11-30 09:00')->addHours($hora),
        ]);
    }

    expect($contar())->toBe($comUm);
});

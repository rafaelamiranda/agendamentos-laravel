<?php

use App\Models\Agendamento;
use App\Models\Bloqueio;
use App\Models\Jornada;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 08:00'));
    $this->actingAs(User::factory()->administrador()->create());
    $this->profissional = Profissional::factory()->create();
});

describe('jornadas', function () {
    test('adiciona, edita e remove', function () {
        $this->post(route('profissionais.jornadas.store', $this->profissional), [
            'dia_semana' => 1, 'inicio' => '09:00', 'fim' => '18:00',
        ])->assertRedirect(route('profissionais.edit', $this->profissional));

        $jornada = Jornada::sole();
        expect($jornada->inicio)->toBe('09:00:00');

        $this->put(route('profissionais.jornadas.update', [$this->profissional, $jornada]), [
            'dia_semana' => 1, 'inicio' => '10:00', 'fim' => '16:00',
        ])->assertSessionHasNoErrors();
        expect($jornada->fresh()->fim)->toBe('16:00:00');

        $this->delete(route('profissionais.jornadas.destroy', [$this->profissional, $jornada]));
        expect(Jornada::count())->toBe(0);
    });

    test('recusa jornada que se sobrepõe a outra do mesmo dia, mas aceita as que encostam', function () {
        Jornada::factory()->for($this->profissional)->create(['dia_semana' => 1, 'inicio' => '09:00:00', 'fim' => '12:00:00']);

        $this->post(route('profissionais.jornadas.store', $this->profissional), [
            'dia_semana' => 1, 'inicio' => '11:00', 'fim' => '15:00',
        ])->assertSessionHasErrors(['inicio' => 'Já existe uma jornada neste dia que cruza este horário.']);

        $this->post(route('profissionais.jornadas.store', $this->profissional), [
            'dia_semana' => 1, 'inicio' => '12:00', 'fim' => '18:00',
        ])->assertSessionHasNoErrors();

        $this->post(route('profissionais.jornadas.store', $this->profissional), [
            'dia_semana' => 2, 'inicio' => '11:00', 'fim' => '15:00',
        ])->assertSessionHasNoErrors();
    });

    test('editar uma jornada não conflita com ela mesma', function () {
        $jornada = Jornada::factory()->for($this->profissional)->create(['dia_semana' => 1, 'inicio' => '09:00:00', 'fim' => '12:00:00']);

        $this->put(route('profissionais.jornadas.update', [$this->profissional, $jornada]), [
            'dia_semana' => 1, 'inicio' => '09:00', 'fim' => '13:00',
        ])->assertSessionHasNoErrors();
    });

    test('valida dia e horários', function () {
        $this->post(route('profissionais.jornadas.store', $this->profissional), [
            'dia_semana' => 7, 'inicio' => '18:00', 'fim' => '09:00',
        ])->assertSessionHasErrors([
            'dia_semana' => 'O campo dia da semana deve estar entre 0 e 6.',
            'fim' => 'O fim deve ser depois do início.',
        ]);
    });

    test('jornada de outro profissional na URL dá 404', function () {
        $outro = Profissional::factory()->create();
        $jornadaDoOutro = Jornada::factory()->for($outro)->create();

        $this->delete(route('profissionais.jornadas.destroy', [$this->profissional, $jornadaDoOutro]))->assertNotFound();

        expect(Jornada::count())->toBe(1);
    });
});

describe('bloqueios', function () {
    test('adiciona, edita e remove', function () {
        $this->post(route('profissionais.bloqueios.store', $this->profissional), [
            'inicio' => '2026-12-01T12:00', 'fim' => '2026-12-01T13:00', 'motivo' => 'Almoço',
        ])->assertRedirect(route('profissionais.edit', $this->profissional));

        $bloqueio = Bloqueio::sole();
        expect($bloqueio->inicio->format('Y-m-d H:i'))->toBe('2026-12-01 12:00')
            ->and($bloqueio->motivo)->toBe('Almoço');

        $this->put(route('profissionais.bloqueios.update', [$this->profissional, $bloqueio]), [
            'inicio' => '2026-12-01T12:30', 'fim' => '2026-12-01T13:30', 'motivo' => null,
        ])->assertSessionHasNoErrors();
        expect($bloqueio->fresh()->motivo)->toBeNull();

        $this->delete(route('profissionais.bloqueios.destroy', [$this->profissional, $bloqueio]));
        expect(Bloqueio::count())->toBe(0);
    });

    test('não bloqueia período que já tem agendamento ocupando horário', function () {
        Agendamento::factory()->for($this->profissional)->confirmado()->create([
            'inicio' => CarbonImmutable::parse('2026-12-01 10:00'),
            'fim' => CarbonImmutable::parse('2026-12-01 11:00'),
        ]);

        $this->post(route('profissionais.bloqueios.store', $this->profissional), [
            'inicio' => '2026-12-01T08:00', 'fim' => '2026-12-01T18:00', 'motivo' => 'Folga',
        ])->assertSessionHasErrors(['inicio' => 'Há 1 agendamento neste período. Resolva-o antes de bloquear.']);

        // Encostar no agendamento não é conflito.
        $this->post(route('profissionais.bloqueios.store', $this->profissional), [
            'inicio' => '2026-12-01T11:00', 'fim' => '2026-12-01T12:00',
        ])->assertSessionHasNoErrors();
    });

    test('agendamento cancelado não impede o bloqueio', function () {
        Agendamento::factory()->for($this->profissional)->cancelado()->create([
            'inicio' => CarbonImmutable::parse('2026-12-01 10:00'),
        ]);

        $this->post(route('profissionais.bloqueios.store', $this->profissional), [
            'inicio' => '2026-12-01T08:00', 'fim' => '2026-12-01T18:00',
        ])->assertSessionHasNoErrors();
    });

    test('valida o formato e a ordem das datas', function () {
        $this->post(route('profissionais.bloqueios.store', $this->profissional), [
            'inicio' => '01/12/2026 10:00', 'fim' => '2026-12-01T09:00',
        ])->assertSessionHasErrors(['inicio']);

        $this->post(route('profissionais.bloqueios.store', $this->profissional), [
            'inicio' => '2026-12-01T10:00', 'fim' => '2026-12-01T09:00',
        ])->assertSessionHasErrors(['fim' => 'O fim deve ser depois do início.']);
    });

    test('bloqueio de outro profissional na URL dá 404', function () {
        $bloqueioDoOutro = Bloqueio::factory()->for(Profissional::factory())->create();

        $this->delete(route('profissionais.bloqueios.destroy', [$this->profissional, $bloqueioDoOutro]))->assertNotFound();
    });

    test('o bloqueio tira os horários da disponibilidade da API', function () {
        Jornada::factory()->for($this->profissional)->create(['dia_semana' => 2, 'inicio' => '09:00:00', 'fim' => '12:00:00']);
        $servico = Servico::factory()->create(['duracao_minutos' => 60]);
        $this->profissional->servicos()->attach($servico);

        $this->post(route('profissionais.bloqueios.store', $this->profissional), [
            'inicio' => '2026-12-01T09:00', 'fim' => '2026-12-01T11:00',
        ]);

        $this->getJson(route('api.disponibilidade', [$this->profissional, 'servico_id' => $servico->id, 'data' => '2026-12-01']))
            ->assertJsonPath('data.0.inicio', '2026-12-01T11:00:00-03:00')
            ->assertJsonCount(1, 'data');
    });
});

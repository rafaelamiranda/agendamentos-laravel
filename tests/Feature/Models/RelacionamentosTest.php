<?php

use App\Models\Agendamento;
use App\Models\Bloqueio;
use App\Models\Cliente;
use App\Models\Jornada;
use App\Models\Profissional;
use App\Models\Servico;
use Illuminate\Database\QueryException;

test('profissional e serviço se relacionam pela tabela profissional_servico', function () {
    $profissional = Profissional::factory()->create();
    $servicos = Servico::factory(2)->create();

    $profissional->servicos()->attach($servicos);

    expect($profissional->servicos)->toHaveCount(2)
        ->and($servicos->first()->profissionais->first()->is($profissional))->toBeTrue();
});

test('o mesmo serviço não pode ser vinculado duas vezes ao profissional', function () {
    $profissional = Profissional::factory()->create();
    $servico = Servico::factory()->create();

    $profissional->servicos()->attach($servico);
    $profissional->servicos()->attach($servico);
})->throws(QueryException::class);

test('profissional tem jornadas, bloqueios e agendamentos', function () {
    $profissional = Profissional::factory()
        ->has(Jornada::factory(2))
        ->has(Bloqueio::factory())
        ->has(Agendamento::factory(3))
        ->create();

    expect($profissional->jornadas)->toHaveCount(2)
        ->and($profissional->bloqueios)->toHaveCount(1)
        ->and($profissional->agendamentos)->toHaveCount(3)
        ->and($profissional->jornadas->first()->profissional->is($profissional))->toBeTrue()
        ->and($profissional->bloqueios->first()->profissional->is($profissional))->toBeTrue();
});

test('agendamento pertence a profissional, serviço e cliente', function () {
    $agendamento = Agendamento::factory()->create();

    expect($agendamento->profissional)->toBeInstanceOf(Profissional::class)
        ->and($agendamento->servico)->toBeInstanceOf(Servico::class)
        ->and($agendamento->cliente)->toBeInstanceOf(Cliente::class)
        ->and($agendamento->cliente->agendamentos->first()->is($agendamento))->toBeTrue()
        ->and($agendamento->servico->agendamentos->first()->is($agendamento))->toBeTrue();
});

test('não é possível apagar registros que têm agendamentos', function (string $relacao) {
    $agendamento = Agendamento::factory()->create();

    $agendamento->{$relacao}->delete();
})->with(['profissional', 'servico', 'cliente'])->throws(QueryException::class);

test('apagar o profissional apaga jornadas, bloqueios e vínculos com serviços', function () {
    $profissional = Profissional::factory()
        ->has(Jornada::factory())
        ->has(Bloqueio::factory())
        ->hasAttached(Servico::factory())
        ->create();

    $profissional->delete();

    expect(Jornada::count())->toBe(0)
        ->and(Bloqueio::count())->toBe(0)
        ->and(Servico::first()->profissionais)->toBeEmpty();
});

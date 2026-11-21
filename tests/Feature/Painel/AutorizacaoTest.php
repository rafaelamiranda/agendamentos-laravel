<?php

use App\Models\Bloqueio;
use App\Models\Jornada;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\User;

/**
 * Todas as rotas do painel, com o método e a URL. Monta os registros na
 * hora, porque o dataset é avaliado dentro do teste.
 *
 * @return list<array{0: string, 1: string}>
 */
function rotasDoPainel(): array
{
    $profissional = Profissional::factory()->create();
    $servico = Servico::factory()->create();
    $jornada = Jornada::factory()->for($profissional)->create();
    $bloqueio = Bloqueio::factory()->for($profissional)->create();

    return [
        ['get', route('agenda')],
        ['get', route('profissionais.index')],
        ['get', route('profissionais.create')],
        ['post', route('profissionais.store')],
        ['get', route('profissionais.edit', $profissional)],
        ['put', route('profissionais.update', $profissional)],
        ['delete', route('profissionais.destroy', $profissional)],
        ['get', route('servicos.index')],
        ['get', route('servicos.create')],
        ['post', route('servicos.store')],
        ['get', route('servicos.edit', $servico)],
        ['put', route('servicos.update', $servico)],
        ['delete', route('servicos.destroy', $servico)],
        ['post', route('profissionais.jornadas.store', $profissional)],
        ['put', route('profissionais.jornadas.update', [$profissional, $jornada])],
        ['delete', route('profissionais.jornadas.destroy', [$profissional, $jornada])],
        ['post', route('profissionais.bloqueios.store', $profissional)],
        ['put', route('profissionais.bloqueios.update', [$profissional, $bloqueio])],
        ['delete', route('profissionais.bloqueios.destroy', [$profissional, $bloqueio])],
    ];
}

test('visitante é mandado para o login em todas as rotas do painel', function () {
    foreach (rotasDoPainel() as [$metodo, $url]) {
        $this->{$metodo}($url)->assertRedirect(route('login'));
    }
});

test('usuário que não é administrador recebe 403 em todas as rotas do painel', function () {
    $this->actingAs(User::factory()->create());

    foreach (rotasDoPainel() as [$metodo, $url]) {
        expect($this->{$metodo}($url)->status())->toBe(403, "{$metodo} {$url}");
    }
});

test('a raiz e o dashboard levam para a agenda', function () {
    $this->get('/')->assertRedirect('/agenda');

    $this->actingAs(User::factory()->administrador()->create())
        ->get(route('dashboard'))
        ->assertRedirect('/agenda');
});

test('o cadastro público está desligado', function () {
    $this->get('/register')->assertNotFound();
});

test('a flag administrador não pode ser alterada pelo perfil', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Outro nome',
        'email' => $user->email,
        'administrador' => true,
    ]);

    expect($user->fresh()->administrador)->toBeFalse();
});

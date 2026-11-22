<?php

use App\Enums\StatusPagamento;
use App\Jobs\EstornarPagamento;
use App\Models\Agendamento;
use App\Models\Pagamento;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
    $this->pendente = Pagamento::factory()
        ->for(Agendamento::factory()->cancelado())
        ->aprovado()
        ->create(['requer_estorno' => true, 'valor_centavos' => 3000]);
    Pagamento::factory()->aprovado()->create(['requer_estorno' => false]);
});

test('lista só os pagamentos com estorno pendente', function () {
    $this->actingAs(User::factory()->administrador()->create())
        ->get(route('estornos.index'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('estornos/index')
            ->has('estornos', 1)
            ->where('estornos.0.id', $this->pendente->id)
            ->where('estornos.0.valor', 'R$ 30,00')
            ->where('estornos.0.situacao', 'Cancelado pelo cliente'));
});

test('a sidebar recebe o número de estornos pendentes, só para administradores', function () {
    $this->actingAs(User::factory()->administrador()->create())
        ->get(route('agenda'))
        ->assertInertia(fn (Assert $pagina) => $pagina->where('pendencias.estornos', 1));

    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $pagina) => $pagina->where('pendencias', null));
});

test('tentar de novo recoloca o estorno na fila', function () {
    $this->actingAs(User::factory()->administrador()->create())
        ->post(route('estornos.tentar-de-novo', $this->pendente))
        ->assertRedirect();

    Queue::assertPushed(EstornarPagamento::class, fn ($job) => $job->pagamentoId === $this->pendente->id);
});

test('marcar como devolvido tira a pendência e registra o estorno', function () {
    $this->actingAs(User::factory()->administrador()->create())
        ->post(route('estornos.marcar-como-devolvido', $this->pendente))
        ->assertRedirect();

    expect($this->pendente->fresh())
        ->requer_estorno->toBeFalse()
        ->status->toBe(StatusPagamento::Estornado)
        ->estornado_em->not->toBeNull();
});

test('só administradores acessam os estornos', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('estornos.index'))->assertForbidden();
    $this->post(route('estornos.tentar-de-novo', $this->pendente))->assertForbidden();
    $this->post(route('estornos.marcar-como-devolvido', $this->pendente))->assertForbidden();

    expect($this->pendente->fresh()->requer_estorno)->toBeTrue();
    Queue::assertNothingPushed();
});

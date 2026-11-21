<?php

use App\Enums\StatusAgendamento;
use App\Jobs\EstornarPagamento;
use App\Mail\AgendamentoCancelado;
use App\Models\Agendamento;
use App\Models\Pagamento;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 10:00'));
    Queue::fake([EstornarPagamento::class]);
    Mail::fake();

    $this->agendamento = Agendamento::factory()->confirmado()->create(['inicio' => CarbonImmutable::parse('2026-11-30 10:00')]);
    Pagamento::factory()->for($this->agendamento)->aprovado()->create();

    $this->link = fn (string $rota = 'agendamentos.cancelamento') => URL::temporarySignedRoute(
        $rota,
        $this->agendamento->inicio,
        ['agendamento' => $this->agendamento->id],
    );
});

test('o link do e-mail mostra a confirmação sem cancelar nada', function () {
    $this->get(($this->link)())
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('publico/cancelamento')
            ->where('pode_cancelar', true)
            ->where('agendamento.quando', 'Segunda-feira, 30 de novembro de 2026 às 10:00')
            ->where('aviso_sinal', 'O sinal será devolvido pelo mesmo meio de pagamento.')
            ->where('situacao_sinal', null));

    // Abrir o link (ou um leitor de e-mail abrindo por conta própria) não cancela.
    expect($this->agendamento->fresh()->status)->toBe(StatusAgendamento::Confirmado);
});

test('avisa antes que o sinal não volta quando já está fora do prazo', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-29 12:00'));

    $this->get(($this->link)())
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('aviso_sinal', 'O cancelamento foi feito com menos antecedência que o prazo da política, então o sinal não é devolvido.'));
});

test('confirmar pelo POST assinado cancela e mostra o resultado', function () {
    $this->post(($this->link)('agendamentos.cancelamento.confirmar'))->assertRedirect();

    expect($this->agendamento->fresh()->status)->toBe(StatusAgendamento::Cancelado);
    Queue::assertPushed(EstornarPagamento::class);
    Mail::assertQueued(AgendamentoCancelado::class);

    $this->get(($this->link)())
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('pode_cancelar', false)
            ->where('situacao_sinal', 'O sinal será devolvido pelo mesmo meio de pagamento.'));
});

test('link adulterado, sem assinatura ou de outro agendamento é recusado', function () {
    $outro = Agendamento::factory()->confirmado()->create(['inicio' => now()->addDays(5)]);
    $assinadoParaOutro = URL::temporarySignedRoute('agendamentos.cancelamento.confirmar', $outro->inicio, ['agendamento' => $outro->id]);
    $trocandoId = str_replace("/agendamentos/{$outro->id}/", "/agendamentos/{$this->agendamento->id}/", $assinadoParaOutro);

    $this->post($trocandoId)->assertForbidden();
    $this->post(route('agendamentos.cancelamento.confirmar', $this->agendamento))->assertForbidden();
    $this->get(route('agendamentos.cancelamento', $this->agendamento))->assertForbidden();

    expect($this->agendamento->fresh()->status)->toBe(StatusAgendamento::Confirmado);
});

test('o link expira no horário do atendimento', function () {
    $link = ($this->link)();
    $this->travelTo($this->agendamento->inicio->addMinute());

    $this->get($link)->assertForbidden();
});

test('cancelar pelo painel devolve o sinal mesmo fora do prazo', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-30 08:00'));
    $this->actingAs(User::factory()->administrador()->create());

    $this->post(route('agenda.cancelamento', $this->agendamento))
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success');

    expect($this->agendamento->fresh()->status)->toBe(StatusAgendamento::Cancelado);
    Queue::assertPushed(EstornarPagamento::class);
});

test('cancelar pelo painel exige administrador', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('agenda.cancelamento', $this->agendamento))->assertForbidden();

    expect($this->agendamento->fresh()->status)->toBe(StatusAgendamento::Confirmado);
});

test('cancelar pelo painel o que não pode ser cancelado mostra um aviso', function () {
    $this->actingAs(User::factory()->administrador()->create());
    $this->agendamento->update(['status' => StatusAgendamento::Concluido]);

    $this->post(route('agenda.cancelamento', $this->agendamento))
        ->assertInertiaFlash('toast.message', 'Este agendamento não pode mais ser cancelado.');
});

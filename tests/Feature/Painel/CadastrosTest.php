<?php

use App\Models\Agendamento;
use App\Models\Bloqueio;
use App\Models\Jornada;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->administrador()->create());
});

describe('profissionais', function () {
    test('lista os profissionais', function () {
        Profissional::factory()->hasAttached(Servico::factory(2))->create(['nome' => 'Ana']);

        $this->get(route('profissionais.index'))
            ->assertInertia(fn (Assert $pagina) => $pagina
                ->component('profissionais/index')
                ->where('profissionais.0.nome', 'Ana')
                ->where('profissionais.0.servicos_count', 2));
    });

    test('cadastra com serviços e vai para a edição (para definir jornadas)', function () {
        $servicos = Servico::factory(2)->create();

        $resposta = $this->post(route('profissionais.store'), [
            'nome' => 'Ana Souza',
            'email' => ' ANA@Exemplo.com ',
            'ativo' => 'on',
            'servicos' => $servicos->pluck('id')->all(),
        ]);

        $profissional = Profissional::sole();
        $resposta->assertRedirect(route('profissionais.edit', $profissional));

        expect($profissional)
            ->email->toBe('ana@exemplo.com')
            ->ativo->toBeTrue()
            ->and($profissional->servicos)->toHaveCount(2);
    });

    test('checkbox desmarcado desativa e tira todos os serviços', function () {
        $profissional = Profissional::factory()->hasAttached(Servico::factory())->create();

        $this->put(route('profissionais.update', $profissional), [
            'nome' => $profissional->nome,
            'email' => $profissional->email,
        ])->assertRedirect();

        expect($profissional->fresh())
            ->ativo->toBeFalse()
            ->servicos->toBeEmpty();
    });

    test('e-mail precisa ser único, ignorando o próprio cadastro', function () {
        Profissional::factory()->create(['email' => 'ana@exemplo.com']);
        $bruno = Profissional::factory()->create(['email' => 'bruno@exemplo.com']);

        $this->put(route('profissionais.update', $bruno), ['nome' => 'Bruno', 'email' => 'ana@exemplo.com'])
            ->assertSessionHasErrors(['email' => 'O valor informado em e-mail já está em uso.']);

        $this->put(route('profissionais.update', $bruno), ['nome' => 'Bruno', 'email' => 'bruno@exemplo.com'])
            ->assertSessionHasNoErrors();
    });

    test('a edição traz jornadas e só os bloqueios que não terminaram', function () {
        $this->travelTo(CarbonImmutable::parse('2026-11-30 08:00'));
        $profissional = Profissional::factory()->create();
        Jornada::factory()->for($profissional)->create(['dia_semana' => 2, 'inicio' => '09:00:00', 'fim' => '12:00:00']);
        Bloqueio::factory()->for($profissional)->create(['inicio' => now()->subDays(2), 'fim' => now()->subDay()]);
        Bloqueio::factory()->for($profissional)->create([
            'inicio' => CarbonImmutable::parse('2026-12-01 12:00'),
            'fim' => CarbonImmutable::parse('2026-12-01 13:00'),
        ]);

        $this->get(route('profissionais.edit', $profissional))
            ->assertInertia(fn (Assert $pagina) => $pagina
                ->component('profissionais/form')
                ->where('profissional.jornadas.0', ['id' => Jornada::sole()->id, 'dia_semana' => 2, 'inicio' => '09:00', 'fim' => '12:00'])
                ->has('profissional.bloqueios', 1)
                ->where('profissional.bloqueios.0.inicio', '2026-12-01T12:00'));
    });

    test('exclui profissional sem agendamentos', function () {
        $profissional = Profissional::factory()->has(Jornada::factory())->create();

        $this->delete(route('profissionais.destroy', $profissional))->assertRedirect(route('profissionais.index'));

        expect(Profissional::count())->toBe(0);
    });

    test('não exclui profissional com agendamentos e explica o porquê', function () {
        $profissional = Profissional::factory()->has(Agendamento::factory())->create();

        $this->from(route('profissionais.index'))
            ->delete(route('profissionais.destroy', $profissional))
            ->assertRedirect(route('profissionais.index'))
            ->assertInertiaFlash('toast.type', 'error');

        expect(Profissional::count())->toBe(1);
    });
});

describe('serviços', function () {
    test('cadastra com preço e sinal digitados em reais', function (string $preco, string $sinal, int $precoCentavos, int $sinalCentavos) {
        $this->post(route('servicos.store'), [
            'nome' => 'Corte',
            'duracao_minutos' => 45,
            'preco' => $preco,
            'sinal' => $sinal,
            'ativo' => '1',
        ])->assertRedirect(route('servicos.index'));

        expect(Servico::sole())
            ->preco_centavos->toBe($precoCentavos)
            ->sinal_centavos->toBe($sinalCentavos)
            ->duracao_minutos->toBe(45);
    })->with([
        'vírgula' => ['120,50', '30', 12050, 3000],
        'milhar' => ['1.234,56', '0,99', 123456, 99],
        'ponto decimal' => ['120.5', '30.05', 12050, 3005],
        'com R$' => ['R$ 80,00', 'R$ 20,00', 8000, 2000],
    ]);

    test('recusa valores inválidos e sinal maior que o preço', function () {
        $this->post(route('servicos.store'), [
            'nome' => 'Corte', 'duracao_minutos' => 45, 'preco' => 'dez reais', 'sinal' => '10',
        ])->assertSessionHasErrors(['preco' => 'Informe o preço em reais, por exemplo 120,00.']);

        $this->post(route('servicos.store'), [
            'nome' => 'Corte', 'duracao_minutos' => 45, 'preco' => '50,00', 'sinal' => '60,00',
        ])->assertSessionHasErrors(['sinal' => 'O sinal não pode ser maior que o preço.']);

        $this->post(route('servicos.store'), [
            'nome' => 'Corte', 'duracao_minutos' => 2, 'preco' => '50,00', 'sinal' => '10,00',
        ])->assertSessionHasErrors(['duracao_minutos' => 'O campo duração deve ser pelo menos 5.']);

        expect(Servico::count())->toBe(0);
    });

    test('a edição mostra os valores formatados em reais', function () {
        $servico = Servico::factory()->create(['preco_centavos' => 123456, 'sinal_centavos' => 5]);

        $this->get(route('servicos.edit', $servico))
            ->assertInertia(fn (Assert $pagina) => $pagina
                ->component('servicos/form')
                ->where('servico.preco', '1.234,56')
                ->where('servico.sinal', '0,05'));
    });

    test('mudar o preço não altera agendamentos já feitos', function () {
        $servico = Servico::factory()->create(['preco_centavos' => 10000, 'sinal_centavos' => 3000]);
        $agendamento = Agendamento::factory()->for($servico)->create();

        $this->put(route('servicos.update', $servico), [
            'nome' => $servico->nome, 'duracao_minutos' => 60, 'preco' => '200,00', 'sinal' => '50,00', 'ativo' => '1',
        ]);

        expect($servico->fresh()->preco_centavos)->toBe(20000)
            ->and($agendamento->fresh()->valor_centavos)->toBe(10000);
    });

    test('não exclui serviço com agendamentos', function () {
        $servico = Servico::factory()->has(Agendamento::factory())->create();

        $this->delete(route('servicos.destroy', $servico))->assertInertiaFlash('toast.type', 'error');

        expect(Servico::find($servico->id))->not->toBeNull();
    });

    test('exclui serviço sem agendamentos', function () {
        $servico = Servico::factory()->create();

        $this->delete(route('servicos.destroy', $servico))->assertRedirect(route('servicos.index'));

        expect(Servico::count())->toBe(0);
    });
});

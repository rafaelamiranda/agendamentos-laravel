<?php

use App\Enums\StatusAgendamento;
use App\Enums\StatusPagamento;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Jornada;
use App\Models\Pagamento;
use App\Models\Profissional;
use App\Models\Servico;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

// "Agora": sexta 27/11/2026 08:00. Reservas para segunda 30/11.
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 08:00'));

    // Cada reserva criada gera um Pix: o Mercado Pago é simulado. A resposta
    // fica numa propriedade para um teste poder simular o gateway fora do ar
    // (um segundo Http::fake não substituiria este: vale o primeiro stub).
    $this->respostaMercadoPago = fn () => Http::response(
        respostaPagamentoMercadoPago(['id' => fake()->unique()->numberBetween(1, 999_999_999)]),
        201,
    );
    Http::fake(['api.mercadopago.com/v1/payments' => fn () => ($this->respostaMercadoPago)()]);

    config([
        'agendamentos.antecedencia_minima_minutos' => 120,
        'agendamentos.antecedencia_maxima_dias' => 60,
        'agendamentos.intervalo_grade_minutos' => 30,
        'agendamentos.prazo_pagamento_minutos' => 15,
    ]);

    $this->servico = Servico::factory()->create([
        'duracao_minutos' => 60,
        'preco_centavos' => 12000,
        'sinal_centavos' => 3000,
    ]);
    $this->profissional = Profissional::factory()->hasAttached($this->servico)->create();

    foreach ([1, 6] as $dia) {
        Jornada::factory()->for($this->profissional)->create(['dia_semana' => $dia, 'inicio' => '09:00:00', 'fim' => '18:00:00']);
    }

    $this->payload = fn (array $alteracoes = []) => array_replace_recursive([
        'profissional_id' => $this->profissional->id,
        'servico_id' => $this->servico->id,
        'inicio' => '2026-11-30T10:00:00-03:00',
        'cliente' => [
            'nome' => 'Maria Silva',
            'email' => 'maria@example.com',
            'telefone' => '(11) 91234-5678',
        ],
    ], $alteracoes);
});

test('cria um agendamento pendente com preço e sinal copiados do serviço', function () {
    $resposta = $this->postJson(route('api.agendamentos.store'), ($this->payload)());

    $resposta->assertCreated()
        ->assertJsonPath('data.status.valor', 'pendente')
        ->assertJsonPath('data.status.rotulo', 'Pendente')
        ->assertJsonPath('data.inicio', '2026-11-30T10:00:00-03:00')
        ->assertJsonPath('data.fim', '2026-11-30T11:00:00-03:00')
        ->assertJsonPath('data.expira_em', '2026-11-27T08:15:00-03:00')
        ->assertJsonPath('data.valor_centavos', 12000)
        ->assertJsonPath('data.sinal_centavos', 3000)
        ->assertJsonPath('data.profissional.id', $this->profissional->id)
        ->assertJsonPath('data.servico.id', $this->servico->id)
        ->assertJsonPath('data.cliente.email', 'maria@example.com');

    $agendamento = Agendamento::sole();
    expect($agendamento->status)->toBe(StatusAgendamento::Pendente)
        ->and($agendamento->cliente->nome)->toBe('Maria Silva');
});

test('mudar o preço do serviço depois não altera o agendamento', function () {
    $this->postJson(route('api.agendamentos.store'), ($this->payload)())->assertCreated();

    $this->servico->update(['preco_centavos' => 99900, 'sinal_centavos' => 50000]);

    expect(Agendamento::sole())
        ->valor_centavos->toBe(12000)
        ->sinal_centavos->toBe(3000);
});

test('recusa com 409 um horário já ocupado', function (string $state, string $inicio, string $fim) {
    Agendamento::factory()->for($this->profissional)->{$state}()->create([
        'inicio' => CarbonImmutable::parse("2026-11-30 {$inicio}"),
        'fim' => CarbonImmutable::parse("2026-11-30 {$fim}"),
    ]);

    $this->postJson(route('api.agendamentos.store'), ($this->payload)())
        ->assertConflict()
        ->assertJsonPath('message', 'O horário escolhido não está disponível. Consulte os horários livres e tente outro.');

    expect(Agendamento::count())->toBe(1);
})->with([
    'mesmo horário confirmado' => ['confirmado', '10:00', '11:00'],
    'mesmo horário pendente no prazo' => ['pendente', '10:00', '11:00'],
    'sobreposição parcial' => ['confirmado', '10:30', '11:30'],
]);

test('o mesmo horário não pode ser reservado duas vezes', function () {
    $this->postJson(route('api.agendamentos.store'), ($this->payload)())->assertCreated();

    $this->postJson(route('api.agendamentos.store'), ($this->payload)([
        'cliente' => ['email' => 'outra@example.com'],
    ]))->assertConflict();

    expect(Agendamento::count())->toBe(1);
});

test('horário de pendente vencido ou cancelado pode ser reservado', function (string $state) {
    Agendamento::factory()->for($this->profissional)->{$state}()->create([
        'inicio' => CarbonImmutable::parse('2026-11-30 10:00'),
        'fim' => CarbonImmutable::parse('2026-11-30 11:00'),
    ]);

    $this->postJson(route('api.agendamentos.store'), ($this->payload)())->assertCreated();
})->with(['pendenteVencido', 'cancelado', 'expirado']);

test('recusa com 409 horários fora das regras da agenda', function (string $inicio) {
    $this->postJson(route('api.agendamentos.store'), ($this->payload)(['inicio' => $inicio]))
        ->assertConflict();

    expect(Agendamento::count())->toBe(0);
})->with([
    'fora da grade' => '2026-11-30T10:15:00-03:00',
    'antes da jornada' => '2026-11-30T08:00:00-03:00',
    'não cabe até o fim da jornada' => '2026-11-30T17:30:00-03:00',
    'dia sem jornada' => '2026-12-01T10:00:00-03:00',
    'dentro da antecedência mínima' => '2026-11-27T09:00:00-03:00',
    'depois da antecedência máxima' => '2027-02-01T10:00:00-03:00',
    'no passado' => '2026-11-23T10:00:00-03:00',
]);

test('aceita o início em UTC e grava no horário de Brasília', function () {
    $this->postJson(route('api.agendamentos.store'), ($this->payload)(['inicio' => '2026-11-30T13:00:00Z']))
        ->assertCreated()
        ->assertJsonPath('data.inicio', '2026-11-30T10:00:00-03:00');
});

test('reaproveita o cliente pelo e-mail e atualiza nome e telefone', function () {
    $cliente = Cliente::factory()->create(['email' => 'maria@example.com', 'telefone' => '(11) 90000-0000']);

    $this->postJson(route('api.agendamentos.store'), ($this->payload)([
        'cliente' => ['email' => '  MARIA@example.com ', 'nome' => 'Maria S. Silva'],
    ]))->assertCreated();

    expect(Cliente::count())->toBe(1)
        ->and($cliente->fresh())
        ->nome->toBe('Maria S. Silva')
        ->telefone->toBe('(11) 91234-5678')
        ->and(Agendamento::sole()->cliente_id)->toBe($cliente->id);
});

test('valida os campos obrigatórios com mensagens em português', function () {
    $this->postJson(route('api.agendamentos.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'profissional_id' => 'O campo profissional é obrigatório.',
            'servico_id' => 'O campo serviço é obrigatório.',
            'inicio' => 'O campo início é obrigatório.',
            'cliente' => 'O campo cliente é obrigatório.',
        ]);
});

test('valida os dados do cliente', function () {
    $this->postJson(route('api.agendamentos.store'), ($this->payload)([
        'inicio' => 'amanhã cedo',
        'cliente' => ['email' => 'nao-e-email', 'telefone' => '123', 'nome' => ''],
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'inicio' => 'O campo início deve ser uma data válida.',
            'cliente.nome' => 'O campo nome é obrigatório.',
            'cliente.email' => 'O campo e-mail deve ser um endereço de e-mail válido.',
            'cliente.telefone' => 'Informe o telefone com DDD, por exemplo (11) 91234-5678.',
        ]);
});

test('recusa profissional que não realiza o serviço', function () {
    $outroServico = Servico::factory()->create();

    $this->postJson(route('api.agendamentos.store'), ($this->payload)(['servico_id' => $outroServico->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['servico_id' => 'Este profissional não realiza o serviço escolhido.']);
});

test('recusa profissional ou serviço inativo', function (string $campo) {
    $campo === 'profissional_id'
        ? $this->profissional->update(['ativo' => false])
        : $this->servico->update(['ativo' => false]);

    $this->postJson(route('api.agendamentos.store'), ($this->payload)())
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$campo]);
})->with(['profissional_id', 'servico_id']);

test('limita as reservas por IP', function () {
    foreach (range(1, 5) as $tentativa) {
        $this->postJson(route('api.agendamentos.store'), [])->assertUnprocessable();
    }

    $this->postJson(route('api.agendamentos.store'), [])->assertTooManyRequests();
});

test('a reserva devolve o Pix do sinal para pagamento', function () {
    $this->postJson(route('api.agendamentos.store'), ($this->payload)())
        ->assertCreated()
        ->assertJsonPath('data.pagamento.status.valor', 'pendente')
        ->assertJsonPath('data.pagamento.valor_centavos', 3000)
        ->assertJsonPath('data.pagamento.pix.qr_code_base64', 'iVBORw0KGgoAAAANSUhEUgAAAAE=')
        ->assertJsonPath('data.pagamento.expira_em', '2026-11-27T08:15:00-03:00');

    expect(Pagamento::sole())
        ->status->toBe(StatusPagamento::Pendente)
        ->valor_centavos->toBe(3000)
        ->agendamento_id->toBe(Agendamento::sole()->id);
});

test('se o Pix não puder ser gerado, a reserva é desfeita e o horário fica livre', function () {
    Sleep::fake();
    $respostaNormal = $this->respostaMercadoPago;
    $this->respostaMercadoPago = fn () => Http::response(['message' => 'erro'], 500);

    $this->postJson(route('api.agendamentos.store'), ($this->payload)())
        ->assertStatus(503)
        ->assertJsonPath('message', 'Não foi possível gerar o Pix agora. Tente reservar novamente em instantes.');

    expect(Agendamento::sole()->status)->toBe(StatusAgendamento::Expirado)
        ->and(Pagamento::count())->toBe(0);

    // O horário volta para a disponibilidade na hora.
    $this->respostaMercadoPago = $respostaNormal;
    $this->postJson(route('api.agendamentos.store'), ($this->payload)())->assertCreated();
});

<?php

namespace App\Actions;

use App\Enums\StatusAgendamento;
use App\Exceptions\HorarioIndisponivelException;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Profissional;
use App\Models\Servico;
use App\Services\CalculadoraDisponibilidade;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\DB;

/**
 * Cria um agendamento pendente, que segura o horário até o sinal ser pago.
 */
class ReservarHorario
{
    public function __construct(
        private readonly CalculadoraDisponibilidade $calculadora,
        #[Config('agendamentos.prazo_pagamento_minutos')]
        private readonly int $prazoPagamentoMinutos,
    ) {}

    /**
     * @param  array{nome: string, email: string, telefone: string}  $dadosCliente
     *
     * @throws HorarioIndisponivelException
     */
    public function executar(
        Profissional $profissional,
        Servico $servico,
        CarbonImmutable $inicio,
        array $dadosCliente,
    ): Agendamento {
        return DB::transaction(function () use ($profissional, $servico, $inicio, $dadosCliente) {
            // Lock na linha do profissional: duas reservas para o mesmo
            // profissional passam por aqui uma de cada vez. A segunda espera
            // a primeira terminar (commit) e, ao conferir a agenda, já
            // enxerga o agendamento criado pela primeira.
            //
            // Por que não travar só os agendamentos que conflitam? Quando o
            // horário está livre não existe linha para travar. "SELECT ...
            // FOR UPDATE" sem resultado não impede outra transação de
            // inserir no mesmo intervalo (e os gap locks do InnoDB nesse
            // cenário levam a deadlocks). A linha do profissional sempre
            // existe e serve de mutex por profissional, sem bloquear a
            // agenda dos outros.
            //
            // O lock precisa ser a PRIMEIRA consulta da transação. No
            // REPEATABLE READ do MySQL, o snapshot nasce na primeira leitura
            // comum; se ela viesse antes do lock, a segunda reserva olharia
            // uma foto antiga da agenda e não veria a primeira.
            Profissional::query()->whereKey($profissional->getKey())->lockForUpdate()->firstOrFail();

            // Conferência feita de novo aqui dentro, já com o lock: o que o
            // cliente viu na disponibilidade pode ter mudado desde então.
            if (! $this->calculadora->estaLivre($profissional, $servico, $inicio)) {
                throw new HorarioIndisponivelException;
            }

            // Cliente identificado pelo e-mail; nome e telefone ficam com os
            // dados mais recentes informados.
            $cliente = Cliente::updateOrCreate(
                ['email' => $dadosCliente['email']],
                ['nome' => $dadosCliente['nome'], 'telefone' => $dadosCliente['telefone']],
            );

            return Agendamento::create([
                'profissional_id' => $profissional->id,
                'servico_id' => $servico->id,
                'cliente_id' => $cliente->id,
                'inicio' => $inicio,
                'fim' => $inicio->addMinutes($servico->duracao_minutos),
                'status' => StatusAgendamento::Pendente,
                // Copiados do serviço agora: mudanças de preço depois da
                // reserva não alteram o que o cliente contratou.
                'valor_centavos' => $servico->preco_centavos,
                'sinal_centavos' => $servico->sinal_centavos,
                'expira_em' => now()->addMinutes($this->prazoPagamentoMinutos),
            ]);
        });
    }
}

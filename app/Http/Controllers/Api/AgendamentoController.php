<?php

namespace App\Http\Controllers\Api;

use App\Actions\GerarCobrancaPix;
use App\Actions\ReservarHorario;
use App\Enums\StatusAgendamento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CriarAgendamentoRequest;
use App\Http\Resources\AgendamentoResource;
use App\Pagamentos\FalhaGatewayPagamentoException;
use Illuminate\Http\JsonResponse;

/**
 * @group Reservas
 */
class AgendamentoController extends Controller
{
    /**
     * Reservar um horário
     *
     * Cria um agendamento `pendente`, que segura o horário por 15 minutos, e
     * devolve o Pix do sinal (copia e cola e QR Code em base64). Pago o sinal,
     * o webhook confirma o agendamento e o cliente recebe um e-mail.
     *
     * O cliente é identificado pelo e-mail, sem precisar de conta: na próxima
     * reserva, nome e telefone são atualizados. O preço e o sinal ficam
     * gravados no agendamento, e mudanças posteriores no serviço não os alteram.
     *
     * Limite: 5 reservas por minuto por IP.
     *
     * @response 201 scenario="Reserva criada" {"data": {"id": 42, "status": {"valor": "pendente", "rotulo": "Pendente"}, "inicio": "2026-11-30T10:00:00-03:00", "fim": "2026-11-30T11:00:00-03:00", "expira_em": "2026-11-27T08:15:00-03:00", "valor_centavos": 12000, "sinal_centavos": 3000, "profissional": {"id": 1, "nome": "Ana Souza"}, "servico": {"id": 1, "nome": "Corte feminino", "duracao_minutos": 60}, "cliente": {"nome": "Maria Silva", "email": "maria@example.com"}, "pagamento": {"status": {"valor": "pendente", "rotulo": "Aguardando pagamento"}, "valor_centavos": 3000, "expira_em": "2026-11-27T08:15:00-03:00", "pix": {"copia_e_cola": "00020126580014br.gov.bcb.pix0136...6304ABCD", "qr_code_base64": "iVBORw0KGgoAAAANSUhEUgAA..."}}}}
     * @response 409 scenario="Horário ocupado, fora da grade ou da jornada, ou fora da antecedência" {"message": "O horário escolhido não está disponível. Consulte os horários livres e tente outro."}
     * @response 422 scenario="Dados inválidos" {"message": "O campo nome é obrigatório. (e mais 1 erro)", "errors": {"cliente.nome": ["O campo nome é obrigatório."], "servico_id": ["Este profissional não realiza o serviço escolhido."]}}
     * @response 429 scenario="Limite de reservas por IP" {"message": "Muitas requisições. Tente novamente em instantes."}
     * @response 503 scenario="Pix não pôde ser gerado (a reserva é desfeita)" {"message": "Não foi possível gerar o Pix agora. Tente reservar novamente em instantes."}
     */
    public function store(
        CriarAgendamentoRequest $request,
        ReservarHorario $reservar,
        GerarCobrancaPix $gerarCobranca,
    ): JsonResponse {
        $agendamento = $reservar->executar(
            $request->profissional(),
            $request->servico(),
            $request->inicio(),
            $request->dadosCliente(),
        );

        // O Pix é gerado depois do commit da reserva, nunca dentro da
        // transação: uma chamada HTTP de segundos segurando o lock do
        // profissional travaria todas as reservas dele nesse tempo.
        try {
            $gerarCobranca->executar($agendamento);
        } catch (FalhaGatewayPagamentoException $erro) {
            // Compensação: sem Pix não há como pagar, então a reserva é
            // desfeita na hora, em vez de segurar o horário até expirar.
            $agendamento->update([
                'status' => StatusAgendamento::Expirado,
                'expira_em' => now(),
            ]);

            report($erro);

            throw $erro;
        }

        $agendamento->load('profissional', 'servico', 'cliente');

        return AgendamentoResource::make($agendamento)
            ->response()
            ->setStatusCode(201);
    }
}

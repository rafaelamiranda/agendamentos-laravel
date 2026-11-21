<?php

namespace App\Pagamentos;

use App\Enums\StatusPagamento;
use App\Models\Agendamento;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Pix via API de pagamentos do Mercado Pago (/v1/payments).
 */
class MercadoPagoGateway implements GatewayPix
{
    public function __construct(
        #[Config('services.mercadopago.base_url')]
        private readonly string $baseUrl,
        #[Config('services.mercadopago.access_token')]
        private readonly ?string $accessToken,
        #[Config('services.mercadopago.notification_url')]
        private readonly ?string $notificationUrl,
    ) {}

    public function criarCobranca(Agendamento $agendamento): CobrancaPix
    {
        $agendamento->loadMissing('cliente', 'servico');

        $corpo = array_filter([
            // A API recebe reais com casas decimais. Centavos são convertidos
            // só aqui, na borda; o resto do sistema nunca vê float.
            'transaction_amount' => $agendamento->sinal_centavos / 100,
            'description' => "Sinal: {$agendamento->servico->nome} em {$agendamento->inicio->format('d/m/Y H:i')}",
            'payment_method_id' => 'pix',
            'external_reference' => (string) $agendamento->id,
            'notification_url' => $this->notificationUrl,
            // O Pix vence junto com a reserva: depois disso, o Mercado Pago
            // não aceita o pagamento de um horário que já foi liberado.
            'date_of_expiration' => $agendamento->expira_em?->format('Y-m-d\TH:i:s.vP'),
            'payer' => [
                'email' => $agendamento->cliente->email,
                'first_name' => $agendamento->cliente->nome,
            ],
        ], fn ($valor) => $valor !== null);

        $resposta = $this->enviar(fn () => $this->http()
            // Mesma chave em toda tentativa: se a primeira chamada criou o
            // pagamento mas a resposta se perdeu, o retry devolve o mesmo
            // pagamento em vez de criar um segundo Pix.
            ->withHeader('X-Idempotency-Key', "agendamento-{$agendamento->id}")
            ->post('/v1/payments', $corpo));

        $dadosPix = $resposta->json('point_of_interaction.transaction_data');

        return new CobrancaPix(
            gatewayId: (string) $resposta->json('id'),
            status: $this->mapearStatus((string) $resposta->json('status')),
            copiaECola: (string) ($dadosPix['qr_code'] ?? ''),
            qrCodeBase64: (string) ($dadosPix['qr_code_base64'] ?? ''),
            expiraEm: CarbonImmutable::parse((string) $resposta->json('date_of_expiration'))
                ->setTimezone(config('app.timezone')),
        );
    }

    public function consultarPagamento(string $gatewayId): PagamentoConsultado
    {
        $resposta = $this->enviar(fn () => $this->http()->get('/v1/payments/'.rawurlencode($gatewayId)));

        $aprovadoEm = $resposta->json('date_approved');

        return new PagamentoConsultado(
            gatewayId: (string) $resposta->json('id'),
            status: $this->mapearStatus((string) $resposta->json('status')),
            valorCentavos: (int) round(((float) $resposta->json('transaction_amount')) * 100),
            aprovadoEm: $aprovadoEm ? CarbonImmutable::parse($aprovadoEm)->setTimezone(config('app.timezone')) : null,
        );
    }

    public function estornar(string $gatewayId, string $chaveIdempotencia): void
    {
        // Corpo vazio = estorno total. Estorno parcial mandaria "amount".
        $this->enviar(fn () => $this->http()
            ->withHeader('X-Idempotency-Key', $chaveIdempotencia)
            ->post('/v1/payments/'.rawurlencode($gatewayId).'/refunds', (object) []));
    }

    /**
     * Status do Mercado Pago para o status do domínio. Status desconhecidos
     * ficam como pendentes: melhor esperar o próximo aviso do que confirmar
     * ou recusar errado.
     */
    private function mapearStatus(string $status): StatusPagamento
    {
        return match ($status) {
            'approved' => StatusPagamento::Aprovado,
            'rejected' => StatusPagamento::Recusado,
            'cancelled' => StatusPagamento::Cancelado,
            'refunded', 'charged_back' => StatusPagamento::Estornado,
            default => StatusPagamento::Pendente,
        };
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken((string) $this->accessToken)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15)
            // Tenta de novo só em falha de rede ou erro 5xx. Um 4xx (dados
            // inválidos, token errado) daria o mesmo resultado de novo.
            ->retry(3, 300, fn (Throwable $erro) => $erro instanceof ConnectionException
                || ($erro instanceof RequestException && $erro->response->serverError()));
    }

    /** @param  callable(): Response  $chamada */
    private function enviar(callable $chamada): Response
    {
        try {
            return $chamada()->throw();
        } catch (ConnectionException $erro) {
            throw new FalhaGatewayPagamentoException('sem resposta do Mercado Pago', $erro);
        } catch (RequestException $erro) {
            throw new FalhaGatewayPagamentoException("Mercado Pago respondeu {$erro->response->status()}", $erro);
        }
    }
}

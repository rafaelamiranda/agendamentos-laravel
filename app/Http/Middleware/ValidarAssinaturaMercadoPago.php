<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confere a assinatura HMAC-SHA256 que o Mercado Pago envia no header
 * x-signature ("ts=...,v1=..."). Sem isso, qualquer um poderia chamar o
 * webhook. Mesmo assim o webhook não confia no corpo (busca o pagamento na
 * API), mas a assinatura evita gastar fila e API com avisos falsos.
 */
class ValidarAssinaturaMercadoPago
{
    public function __construct(
        #[Config('services.mercadopago.webhook_secret')]
        private readonly ?string $segredo,
        #[Config('services.mercadopago.tolerancia_assinatura_segundos')]
        private readonly int $toleranciaSegundos,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Sem segredo configurado, recusa tudo (fail closed), em vez de
        // aceitar qualquer aviso.
        if (blank($this->segredo)) {
            Log::error('Webhook do Mercado Pago recebido sem MERCADOPAGO_WEBHOOK_SECRET configurado.');

            return $this->recusar();
        }

        $partes = $this->partesDaAssinatura((string) $request->header('x-signature'));
        $ts = $partes['ts'] ?? null;
        $v1 = $partes['v1'] ?? null;

        if (! $ts || ! $v1 || ! $this->dentroDaTolerancia($ts)) {
            return $this->recusar();
        }

        $esperada = hash_hmac('sha256', $this->manifesto($request, $ts), (string) $this->segredo);

        // hash_equals compara em tempo constante: não vaza, pelo tempo de
        // resposta, quantos caracteres da assinatura estavam certos.
        if (! hash_equals($esperada, $v1)) {
            return $this->recusar();
        }

        return $next($request);
    }

    /**
     * "id:{data.id};request-id:{x-request-id};ts:{ts};" Pares ausentes
     * ficam de fora, e o id vai em minúsculas, como pede a documentação.
     */
    private function manifesto(Request $request, string $ts): string
    {
        // O id vem da query string "?data.id=...". O PHP troca "." por "_"
        // nos nomes de parâmetros, então ele chega como "data_id".
        $dataId = $request->query('data_id');
        $requestId = $request->header('x-request-id');

        $partes = [];

        if (is_string($dataId) && $dataId !== '') {
            $partes[] = 'id:'.strtolower($dataId);
        }

        if (is_string($requestId) && $requestId !== '') {
            $partes[] = "request-id:{$requestId}";
        }

        $partes[] = "ts:{$ts}";

        return implode(';', $partes).';';
    }

    /** @return array<string, string> */
    private function partesDaAssinatura(string $header): array
    {
        $partes = [];

        foreach (explode(',', $header) as $parte) {
            [$chave, $valor] = array_pad(explode('=', $parte, 2), 2, '');
            $partes[trim($chave)] = trim($valor);
        }

        return $partes;
    }

    /**
     * Recusa avisos com horário muito antigo ou no futuro: uma notificação
     * válida capturada não pode ser reenviada dias depois (replay). O ts
     * aparece em segundos ou em milissegundos na documentação, então
     * aceita os dois.
     */
    private function dentroDaTolerancia(string $ts): bool
    {
        if (! ctype_digit($ts)) {
            return false;
        }

        $segundos = strlen($ts) > 10 ? intdiv((int) $ts, 1000) : (int) $ts;

        return abs(now()->getTimestamp() - $segundos) <= $this->toleranciaSegundos;
    }

    private function recusar(): Response
    {
        return response()->json(['message' => 'Assinatura inválida.'], 401);
    }
}

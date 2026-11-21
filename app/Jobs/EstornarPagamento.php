<?php

namespace App\Jobs;

use App\Enums\StatusPagamento;
use App\Models\Pagamento;
use App\Pagamentos\GatewayPix;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Devolve o sinal pelo gateway. Roda na fila porque é uma chamada externa
 * que pode falhar: a fila tenta de novo com intervalo crescente, e a chave
 * de idempotência impede que um retry estorne duas vezes.
 */
class EstornarPagamento implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public readonly int $pagamentoId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(GatewayPix $gateway): void
    {
        $pagamento = Pagamento::findOrFail($this->pagamentoId);

        // Já estornado (por este job ou por aviso do webhook): nada a fazer.
        if ($pagamento->status === StatusPagamento::Estornado) {
            return;
        }

        // A chamada externa fica fora da transação, para não segurar lock
        // durante uma requisição HTTP.
        $gateway->estornar($pagamento->gateway_id, "estorno-pagamento-{$pagamento->id}");

        DB::transaction(function () {
            $pagamento = Pagamento::query()->lockForUpdate()->findOrFail($this->pagamentoId);

            if (! $pagamento->status->podeMudarPara(StatusPagamento::Estornado)) {
                return;
            }

            $pagamento->update([
                'status' => StatusPagamento::Estornado,
                'estornado_em' => now(),
                'requer_estorno' => false,
            ]);
        });
    }

    /**
     * Esgotadas as tentativas, o pagamento continua com requer_estorno =
     * true, e o estabelecimento resolve manualmente.
     */
    public function failed(?Throwable $erro): void
    {
        Log::error('Estorno automático falhou; devolver o sinal manualmente.', [
            'pagamento_id' => $this->pagamentoId,
            'erro' => $erro?->getMessage(),
        ]);
    }
}

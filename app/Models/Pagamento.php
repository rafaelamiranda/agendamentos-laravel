<?php

namespace App\Models;

use App\Enums\StatusPagamento;
use Carbon\CarbonImmutable;
use Database\Factories\PagamentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pagamento do sinal de um agendamento.
 *
 * @property int $id
 * @property int $agendamento_id
 * @property string $gateway
 * @property string $gateway_id
 * @property StatusPagamento $status
 * @property int $valor_centavos
 * @property string $pix_copia_e_cola
 * @property string $pix_qr_code_base64
 * @property CarbonImmutable $expira_em
 * @property CarbonImmutable|null $aprovado_em
 * @property CarbonImmutable|null $estornado_em
 * @property bool $requer_estorno
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Pagamento extends Model
{
    /** @use HasFactory<PagamentoFactory> */
    use HasFactory;

    protected $table = 'pagamentos';

    protected $fillable = [
        'agendamento_id',
        'gateway',
        'gateway_id',
        'status',
        'valor_centavos',
        'pix_copia_e_cola',
        'pix_qr_code_base64',
        'expira_em',
        'aprovado_em',
        'estornado_em',
        'requer_estorno',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPagamento::class,
            'valor_centavos' => 'integer',
            'expira_em' => 'datetime',
            'aprovado_em' => 'datetime',
            'estornado_em' => 'datetime',
            'requer_estorno' => 'boolean',
        ];
    }

    /** @return BelongsTo<Agendamento, $this> */
    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class);
    }
}

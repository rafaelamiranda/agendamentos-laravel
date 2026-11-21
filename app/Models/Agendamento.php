<?php

namespace App\Models;

use App\Enums\OrigemCancelamento;
use App\Enums\StatusAgendamento;
use App\Models\Concerns\TemIntervalo;
use Carbon\CarbonImmutable;
use Database\Factories\AgendamentoFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $profissional_id
 * @property int $servico_id
 * @property int $cliente_id
 * @property CarbonImmutable $inicio
 * @property CarbonImmutable $fim
 * @property StatusAgendamento $status
 * @property int $valor_centavos
 * @property int $sinal_centavos
 * @property CarbonImmutable|null $expira_em
 * @property CarbonImmutable|null $cancelado_em
 * @property OrigemCancelamento|null $cancelado_por
 * @property CarbonImmutable|null $lembrete_enviado_em
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Agendamento extends Model
{
    /** @use HasFactory<AgendamentoFactory> */
    use HasFactory, TemIntervalo;

    protected $table = 'agendamentos';

    protected $fillable = [
        'profissional_id',
        'servico_id',
        'cliente_id',
        'inicio',
        'fim',
        'status',
        'valor_centavos',
        'sinal_centavos',
        'expira_em',
        'cancelado_em',
        'cancelado_por',
        'lembrete_enviado_em',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusAgendamento::class,
            'valor_centavos' => 'integer',
            'sinal_centavos' => 'integer',
            'expira_em' => 'datetime',
            'cancelado_em' => 'datetime',
            'cancelado_por' => OrigemCancelamento::class,
            'lembrete_enviado_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<Profissional, $this> */
    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Profissional::class);
    }

    /** @return BelongsTo<Servico, $this> */
    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function podeSerCancelado(): bool
    {
        return in_array($this->status, [StatusAgendamento::Pendente, StatusAgendamento::Confirmado], true)
            && $this->inicio->isFuture();
    }

    /** @return HasOne<Pagamento, $this> */
    public function pagamento(): HasOne
    {
        return $this->hasOne(Pagamento::class);
    }

    /**
     * Agendamentos que tornam o horário indisponível: confirmados, concluídos
     * e pendentes ainda dentro do prazo de pagamento do sinal.
     *
     * O pendente vencido deixa de ocupar o horário assim que expira_em passa,
     * mesmo antes de o job de expiração mudar o status para "expirado". Assim
     * a disponibilidade não depende de o job ter rodado.
     *
     * O "agora" vem do PHP (now()) e não do NOW() do MySQL, para usar o
     * fuso da aplicação e respeitar o relógio congelado nos testes.
     */
    #[Scope]
    protected function ocupandoHorario(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query->whereIn($query->qualifyColumn('status'), [
                StatusAgendamento::Confirmado,
                StatusAgendamento::Concluido,
            ])->orWhere(function (Builder $query) {
                $query->where($query->qualifyColumn('status'), StatusAgendamento::Pendente)
                    ->where($query->qualifyColumn('expira_em'), '>', now());
            });
        });
    }
}

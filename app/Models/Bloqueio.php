<?php

namespace App\Models;

use App\Models\Concerns\TemIntervalo;
use Carbon\CarbonImmutable;
use Database\Factories\BloqueioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Período em que o profissional não atende: folga, férias, almoço.
 *
 * @property int $id
 * @property int $profissional_id
 * @property CarbonImmutable $inicio
 * @property CarbonImmutable $fim
 * @property string|null $motivo
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Bloqueio extends Model
{
    /** @use HasFactory<BloqueioFactory> */
    use HasFactory, TemIntervalo;

    protected $table = 'bloqueios';

    protected $fillable = ['profissional_id', 'inicio', 'fim', 'motivo'];

    /** @return BelongsTo<Profissional, $this> */
    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Profissional::class);
    }
}

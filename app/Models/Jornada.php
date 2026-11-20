<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\JornadaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Faixa de trabalho semanal de um profissional. Intervalos como o almoço
 * ficam em Bloqueio, para a jornada continuar sendo uma faixa simples.
 *
 * @property int $id
 * @property int $profissional_id
 * @property int $dia_semana 0 = domingo ... 6 = sábado
 * @property string $inicio formato H:i:s
 * @property string $fim formato H:i:s
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Jornada extends Model
{
    /** @use HasFactory<JornadaFactory> */
    use HasFactory;

    protected $table = 'jornadas';

    protected $fillable = ['profissional_id', 'dia_semana', 'inicio', 'fim'];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
        ];
    }

    /** @return BelongsTo<Profissional, $this> */
    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Profissional::class);
    }
}

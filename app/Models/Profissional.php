<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ProfissionalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nome
 * @property string $email
 * @property bool $ativo
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Profissional extends Model
{
    /** @use HasFactory<ProfissionalFactory> */
    use HasFactory;

    // Explícito porque a pluralização do Laravel é em inglês ("profissionals").
    protected $table = 'profissionais';

    protected $fillable = ['nome', 'email', 'ativo'];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Servico, $this> */
    public function servicos(): BelongsToMany
    {
        return $this->belongsToMany(Servico::class, 'profissional_servico');
    }

    /** @return HasMany<Jornada, $this> */
    public function jornadas(): HasMany
    {
        return $this->hasMany(Jornada::class);
    }

    /** @return HasMany<Bloqueio, $this> */
    public function bloqueios(): HasMany
    {
        return $this->hasMany(Bloqueio::class);
    }

    /** @return HasMany<Agendamento, $this> */
    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class);
    }
}

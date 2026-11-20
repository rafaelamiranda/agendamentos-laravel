<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ServicoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nome
 * @property string|null $descricao
 * @property int $duracao_minutos
 * @property int $preco_centavos
 * @property int $sinal_centavos
 * @property bool $ativo
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Servico extends Model
{
    /** @use HasFactory<ServicoFactory> */
    use HasFactory;

    protected $table = 'servicos';

    protected $fillable = [
        'nome',
        'descricao',
        'duracao_minutos',
        'preco_centavos',
        'sinal_centavos',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'duracao_minutos' => 'integer',
            'preco_centavos' => 'integer',
            'sinal_centavos' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Profissional, $this> */
    public function profissionais(): BelongsToMany
    {
        return $this->belongsToMany(Profissional::class, 'profissional_servico');
    }

    /** @return HasMany<Agendamento, $this> */
    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class);
    }
}

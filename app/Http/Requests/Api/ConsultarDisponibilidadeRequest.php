<?php

namespace App\Http\Requests\Api;

use App\Models\Servico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultarDisponibilidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'servico_id' => ['required', 'integer', Rule::exists('servicos', 'id')->where('ativo', true)],
            'data' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Descrições para a documentação da API (Scribe).
     *
     * @return array<string, array<string, mixed>>
     */
    public function queryParameters(): array
    {
        return [
            'servico_id' => ['description' => 'Id do serviço (ativo). A duração dele define o fim de cada horário.', 'example' => 1],
            'data' => ['description' => 'Dia consultado, no formato AAAA-MM-DD.', 'example' => '2026-11-30'],
        ];
    }

    public function servico(): Servico
    {
        return Servico::findOrFail($this->integer('servico_id'));
    }
}

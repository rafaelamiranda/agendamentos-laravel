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

    public function servico(): Servico
    {
        return Servico::findOrFail($this->integer('servico_id'));
    }
}

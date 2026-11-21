<?php

namespace App\Http\Requests\Painel;

use App\Models\Profissional;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfissionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profissional = $this->route('profissional');

        return $profissional instanceof Profissional
            ? $this->user()?->can('update', $profissional) ?? false
            : $this->user()?->can('create', Profissional::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            // Checkbox desmarcado não é enviado: ausência significa false.
            'ativo' => $this->boolean('ativo'),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'servicos' => $this->input('servicos', []),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('profissionais', 'email')->ignore($this->route('profissional')),
            ],
            'ativo' => ['boolean'],
            'servicos' => ['array'],
            'servicos.*' => ['integer', 'exists:servicos,id'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['servicos' => 'serviços', 'servicos.*' => 'serviço'];
    }
}

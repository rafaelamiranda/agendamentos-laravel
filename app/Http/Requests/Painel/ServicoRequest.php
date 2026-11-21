<?php

namespace App\Http\Requests\Painel;

use App\Models\Servico;
use App\Support\Dinheiro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * O formulário recebe preço e sinal em reais ("120,50"), como a pessoa
 * digita; dados() entrega em centavos para o model.
 */
class ServicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $servico = $this->route('servico');

        return $servico instanceof Servico
            ? $this->user()?->can('update', $servico) ?? false
            : $this->user()?->can('create', Servico::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['ativo' => $this->boolean('ativo')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:1000'],
            'duracao_minutos' => ['required', 'integer', 'min:5', 'max:480'],
            'preco' => ['required', 'string', 'max:20'],
            'sinal' => ['required', 'string', 'max:20'],
            'ativo' => ['boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $preco = Dinheiro::paraCentavos((string) $this->input('preco'));
                $sinal = Dinheiro::paraCentavos((string) $this->input('sinal'));

                if (! $validator->errors()->has('preco') && $preco === null) {
                    $validator->errors()->add('preco', 'Informe o preço em reais, por exemplo 120,00.');
                }

                if (! $validator->errors()->has('sinal') && $sinal === null) {
                    $validator->errors()->add('sinal', 'Informe o sinal em reais, por exemplo 30,00.');
                }

                if ($preco !== null && $sinal !== null && $sinal > $preco) {
                    $validator->errors()->add('sinal', 'O sinal não pode ser maior que o preço.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'descricao' => 'descrição',
            'duracao_minutos' => 'duração',
            'preco' => 'preço',
        ];
    }

    /** @return array<string, mixed> */
    public function dados(): array
    {
        return [
            'nome' => $this->validated('nome'),
            'descricao' => $this->validated('descricao'),
            'duracao_minutos' => (int) $this->validated('duracao_minutos'),
            'preco_centavos' => Dinheiro::paraCentavos((string) $this->validated('preco')),
            'sinal_centavos' => Dinheiro::paraCentavos((string) $this->validated('sinal')),
            'ativo' => (bool) $this->validated('ativo'),
        ];
    }
}

<?php

namespace App\Http\Requests\Api;

use App\Models\Profissional;
use App\Models\Servico;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Valida o formato do pedido e se o profissional atende o serviço. A
 * disponibilidade do horário não é validada aqui: ela depende do estado da
 * agenda e só é conferida com segurança dentro da transação da reserva.
 */
class CriarAgendamentoRequest extends FormRequest
{
    /** Reserva pública: o cliente não tem conta. */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('cliente.email');

        if (is_string($email)) {
            $this->merge([
                'cliente' => [
                    ...(array) $this->input('cliente'),
                    'email' => Str::lower(trim($email)),
                ],
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'profissional_id' => ['required', 'integer', Rule::exists('profissionais', 'id')->where('ativo', true)],
            'servico_id' => ['required', 'integer', Rule::exists('servicos', 'id')->where('ativo', true)],
            // ISO 8601, como devolvido pela disponibilidade (2026-11-30T09:00:00-03:00).
            'inicio' => ['required', 'date'],
            'cliente' => ['required', 'array:nome,email,telefone'],
            'cliente.nome' => ['required', 'string', 'max:255'],
            'cliente.email' => ['required', 'string', 'email', 'max:255'],
            'cliente.telefone' => ['required', 'string', 'regex:/^[0-9()+\-\s]{10,20}$/'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['profissional_id', 'servico_id'])) {
                    return;
                }

                $atende = $this->profissional()->servicos()->whereKey($this->servico()->getKey())->exists();

                if (! $atende) {
                    $validator->errors()->add('servico_id', 'Este profissional não realiza o serviço escolhido.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cliente.telefone.regex' => 'Informe o telefone com DDD, por exemplo (11) 91234-5678.',
        ];
    }

    public function profissional(): Profissional
    {
        return Profissional::findOrFail($this->integer('profissional_id'));
    }

    public function servico(): Servico
    {
        return Servico::findOrFail($this->integer('servico_id'));
    }

    public function inicio(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('inicio')->toString())
            ->setTimezone(config('app.timezone'));
    }

    /** @return array{nome: string, email: string, telefone: string} */
    public function dadosCliente(): array
    {
        /** @var array{nome: string, email: string, telefone: string} $cliente */
        $cliente = $this->validated('cliente');

        return $cliente;
    }
}

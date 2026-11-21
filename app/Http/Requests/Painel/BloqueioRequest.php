<?php

namespace App\Http\Requests\Painel;

use App\Models\Bloqueio;
use App\Models\Profissional;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BloqueioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bloqueio = $this->route('bloqueio');

        return $bloqueio instanceof Bloqueio
            ? $this->user()?->can('update', $bloqueio) ?? false
            : $this->user()?->can('create', Bloqueio::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Formato do <input type="datetime-local">.
        return [
            'inicio' => ['required', 'date_format:Y-m-d\TH:i'],
            'fim' => ['required', 'date_format:Y-m-d\TH:i', 'after:inicio'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Profissional $profissional */
                $profissional = $this->route('profissional');

                // Bloquear não cancela ninguém: se há clientes com horário
                // marcado no período, o administrador decide antes o que fazer
                // com eles (cancelar e avisar, remarcar).
                $conflitos = $profissional->agendamentos()
                    ->ocupandoHorario()
                    ->sobrepondo($this->inicio(), $this->fim())
                    ->count();

                if ($conflitos > 0) {
                    $validator->errors()->add('inicio', trans_choice(
                        'Há :count agendamento neste período. Resolva-o antes de bloquear.|Há :count agendamentos neste período. Resolva-os antes de bloquear.',
                        $conflitos,
                    ));
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['inicio' => 'início'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['fim.after' => 'O fim deve ser depois do início.'];
    }

    public function inicio(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d\TH:i', (string) $this->input('inicio'));
    }

    public function fim(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d\TH:i', (string) $this->input('fim'));
    }
}

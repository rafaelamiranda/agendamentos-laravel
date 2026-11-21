<?php

namespace App\Http\Requests\Painel;

use App\Models\Jornada;
use App\Models\Profissional;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class JornadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $jornada = $this->route('jornada');

        return $jornada instanceof Jornada
            ? $this->user()?->can('update', $jornada) ?? false
            : $this->user()?->can('create', Jornada::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'dia_semana' => ['required', 'integer', 'between:0,6'],
            'inicio' => ['required', 'date_format:H:i'],
            // Jornada dentro do dia: turno que cruza a meia-noite seriam duas.
            'fim' => ['required', 'date_format:H:i', 'after:inicio'],
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
                $jornada = $this->route('jornada');

                // Mesma regra semiaberta da agenda: 09–12 e 12–18 encostam,
                // não se sobrepõem.
                $sobrepoe = $profissional->jornadas()
                    ->where('dia_semana', $this->integer('dia_semana'))
                    ->when($jornada instanceof Jornada, fn ($query) => $query->whereKeyNot($jornada->id))
                    ->where('inicio', '<', $this->input('fim'))
                    ->where('fim', '>', $this->input('inicio'))
                    ->exists();

                if ($sobrepoe) {
                    $validator->errors()->add('inicio', 'Já existe uma jornada neste dia que cruza este horário.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['dia_semana' => 'dia da semana', 'inicio' => 'início'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['fim.after' => 'O fim deve ser depois do início.'];
    }
}

<?php

use App\Models\Cliente;
use App\Models\Jornada;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\User;

test('o seeder cria os dados de demonstração', function () {
    $this->seed();

    expect(User::where('email', 'admin@agendamentos.test')->exists())->toBeTrue()
        ->and(Servico::count())->toBe(4)
        ->and(Profissional::count())->toBe(3)
        ->and(Cliente::count())->toBe(10);

    Profissional::with('jornadas', 'servicos')->get()->each(function (Profissional $profissional) {
        expect($profissional->servicos)->not->toBeEmpty()
            ->and($profissional->jornadas->pluck('dia_semana')->sort()->values()->all())->toBe([1, 2, 3, 4, 5, 6]);

        $sabado = $profissional->jornadas->firstWhere('dia_semana', 6);
        expect($sabado->inicio)->toBe('09:00:00')
            ->and($sabado->fim)->toBe('13:00:00');
    });

    expect(Jornada::where('dia_semana', 0)->exists())->toBeFalse();
});

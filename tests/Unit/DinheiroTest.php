<?php

use App\Support\Dinheiro;

test('converte texto em reais para centavos', function (string $texto, ?int $centavos) {
    expect(Dinheiro::paraCentavos($texto))->toBe($centavos);
})->with([
    ['120', 12000],
    ['120,5', 12050],
    ['120,50', 12050],
    ['0,01', 1],
    ['1.234,56', 123456],
    ['1.000.000', 100000000],
    ['120.5', 12050],
    ['R$ 30,00', 3000],
    // Um float erraria aqui: 0,29 * 100 = 28,999...
    ['0,29', 29],
    'texto' => ['dez', null],
    'três casas' => ['1,234', null],
    'negativo' => ['-10', null],
    'vazio' => ['', null],
]);

test('formata centavos em reais', function (int $centavos, string $texto) {
    expect(Dinheiro::formatar($centavos))->toBe($texto);
})->with([
    [0, '0,00'],
    [5, '0,05'],
    [12050, '120,50'],
    [123456, '1.234,56'],
]);

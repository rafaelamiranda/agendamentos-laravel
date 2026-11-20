<?php

use App\Enums\StatusAgendamento;

test('cada status tem um rótulo em português', function (StatusAgendamento $status, string $rotulo) {
    expect($status->rotulo())->toBe($rotulo);
})->with([
    [StatusAgendamento::Pendente, 'Pendente'],
    [StatusAgendamento::Confirmado, 'Confirmado'],
    [StatusAgendamento::Concluido, 'Concluído'],
    [StatusAgendamento::Cancelado, 'Cancelado'],
    [StatusAgendamento::Expirado, 'Expirado'],
]);

test('informa quais status podem ocupar horário', function (StatusAgendamento $status, bool $ocupa) {
    expect($status->podeOcuparHorario())->toBe($ocupa);
})->with([
    [StatusAgendamento::Pendente, true],
    [StatusAgendamento::Confirmado, true],
    [StatusAgendamento::Concluido, true],
    [StatusAgendamento::Cancelado, false],
    [StatusAgendamento::Expirado, false],
]);

test('os valores gravados no banco são estáveis', function () {
    expect(array_map(fn (StatusAgendamento $status) => $status->value, StatusAgendamento::cases()))
        ->toBe(['pendente', 'confirmado', 'concluido', 'cancelado', 'expirado']);
});

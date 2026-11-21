<?php

use App\Enums\StatusPagamento;

test('transições permitidas entre status de pagamento', function (StatusPagamento $de, StatusPagamento $para, bool $permitida) {
    expect($de->podeMudarPara($para))->toBe($permitida);
})->with([
    'pendente para aprovado' => [StatusPagamento::Pendente, StatusPagamento::Aprovado, true],
    'pendente para recusado' => [StatusPagamento::Pendente, StatusPagamento::Recusado, true],
    'pendente para pendente' => [StatusPagamento::Pendente, StatusPagamento::Pendente, false],
    'aprovado para estornado' => [StatusPagamento::Aprovado, StatusPagamento::Estornado, true],
    'aprovado para pendente (aviso atrasado)' => [StatusPagamento::Aprovado, StatusPagamento::Pendente, false],
    'aprovado para aprovado (aviso repetido)' => [StatusPagamento::Aprovado, StatusPagamento::Aprovado, false],
    'recusado é final' => [StatusPagamento::Recusado, StatusPagamento::Aprovado, false],
    'estornado é final' => [StatusPagamento::Estornado, StatusPagamento::Aprovado, false],
]);

test('cada status tem rótulo em português', function () {
    expect(array_map(fn (StatusPagamento $status) => $status->rotulo(), StatusPagamento::cases()))
        ->toBe(['Aguardando pagamento', 'Aprovado', 'Recusado', 'Cancelado', 'Estornado']);
});

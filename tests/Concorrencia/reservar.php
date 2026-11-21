<?php

/*
| Processo separado usado pelo teste de concorrência: tenta reservar um
| horário com a própria conexão ao MySQL, como um segundo cliente faria.
|
| Uso: php tests/Concorrencia/reservar.php {profissional_id} {servico_id} {inicio}
| Saída: "pronto" ao começar a reserva, depois "reservado:{id}" ou "indisponivel".
*/

use App\Actions\ReservarHorario;
use App\Exceptions\HorarioIndisponivelException;
use App\Models\Profissional;
use App\Models\Servico;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $profissionalId, $servicoId, $inicio] = $argv;

$profissional = Profissional::findOrFail($profissionalId);
$servico = Servico::findOrFail($servicoId);
$reservar = app(ReservarHorario::class);

fwrite(STDOUT, "pronto\n");
fflush(STDOUT);

try {
    $agendamento = $reservar->executar($profissional, $servico, CarbonImmutable::parse($inicio), [
        'nome' => 'Cliente Concorrente',
        'email' => 'concorrente@example.com',
        'telefone' => '(11) 91234-5678',
    ]);

    fwrite(STDOUT, "reservado:{$agendamento->id}\n");
} catch (HorarioIndisponivelException) {
    fwrite(STDOUT, "indisponivel\n");
}

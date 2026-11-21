<?php

namespace App\Services;

use App\Enums\StatusPagamento;
use App\Models\Agendamento;
use App\Models\Bloqueio;
use App\Models\Jornada;
use App\Models\Profissional;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Monta os dados da agenda do painel no formato que a tela desenha: uma
 * lista de colunas, cada uma com jornadas, bloqueios e agendamentos.
 *
 *  - Visão dia: uma coluna por profissional ativo, no dia escolhido.
 *  - Visão semana: uma coluna por dia (segunda a domingo), para um profissional.
 *
 * Quatro consultas no total, qualquer que seja o número de colunas: os
 * dados do período inteiro são carregados de uma vez e distribuídos em
 * memória.
 */
class MontarAgenda
{
    private const HORA_INICIAL_PADRAO = 8;

    private const HORA_FINAL_PADRAO = 18;

    /**
     * @return array{colunas: list<array<string, mixed>>, hora_inicial: int, hora_final: int}
     */
    public function dia(CarbonImmutable $data): array
    {
        $profissionais = Profissional::query()->where('ativo', true)->orderBy('nome')->get();
        $inicio = $data->startOfDay();

        $dados = $this->carregar($profissionais, $inicio, $inicio->addDay());

        $colunas = $profissionais->map(fn (Profissional $profissional) => $this->coluna(
            titulo: $profissional->nome,
            subtitulo: null,
            dia: $inicio,
            profissionalId: $profissional->id,
            dados: $dados,
        ));

        return $this->resultado($colunas);
    }

    /**
     * @return array{colunas: list<array<string, mixed>>, hora_inicial: int, hora_final: int}
     */
    public function semana(Profissional $profissional, CarbonImmutable $data): array
    {
        $segunda = $data->startOfWeek(CarbonImmutable::MONDAY);
        $dados = $this->carregar(collect([$profissional]), $segunda, $segunda->addWeek());

        $colunas = collect(range(0, 6))->map(function (int $deslocamento) use ($segunda, $profissional, $dados) {
            $dia = $segunda->addDays($deslocamento);

            return $this->coluna(
                titulo: ucfirst($dia->translatedFormat('l')),
                subtitulo: $dia->format('d/m'),
                dia: $dia,
                profissionalId: $profissional->id,
                dados: $dados,
            );
        });

        return $this->resultado($colunas);
    }

    /**
     * @param  Collection<int, Profissional>  $profissionais
     * @return array{jornadas: Collection<int, Jornada>, bloqueios: Collection<int, Bloqueio>, agendamentos: Collection<int, Agendamento>}
     */
    private function carregar(Collection $profissionais, CarbonImmutable $inicio, CarbonImmutable $fim): array
    {
        $ids = $profissionais->pluck('id');

        return [
            'jornadas' => Jornada::query()->whereIn('profissional_id', $ids)->orderBy('inicio')->get(),
            'bloqueios' => Bloqueio::query()->whereIn('profissional_id', $ids)->sobrepondo($inicio, $fim)->get(),
            // Só o que ocupa horário: cancelados, expirados e pendentes
            // vencidos não aparecem na grade.
            'agendamentos' => Agendamento::query()
                ->whereIn('profissional_id', $ids)
                ->ocupandoHorario()
                ->sobrepondo($inicio, $fim)
                ->with('cliente', 'servico', 'pagamento')
                ->orderBy('inicio')
                ->get(),
        ];
    }

    /**
     * Uma coluna da grade. Horários vão como "HH:MM" do próprio dia; um
     * bloqueio de vários dias (férias) é recortado para o dia da coluna.
     *
     * @param  array{jornadas: Collection<int, Jornada>, bloqueios: Collection<int, Bloqueio>, agendamentos: Collection<int, Agendamento>}  $dados
     * @return array<string, mixed>
     */
    private function coluna(string $titulo, ?string $subtitulo, CarbonImmutable $dia, int $profissionalId, array $dados): array
    {
        $fimDoDia = $dia->addDay();

        return [
            'chave' => "{$profissionalId}-{$dia->toDateString()}",
            'titulo' => $titulo,
            'subtitulo' => $subtitulo,
            'data' => $dia->toDateString(),
            'profissional_id' => $profissionalId,
            'jornadas' => $dados['jornadas']
                ->where('profissional_id', $profissionalId)
                ->where('dia_semana', $dia->dayOfWeek)
                ->map(fn (Jornada $jornada) => [
                    'inicio' => substr($jornada->inicio, 0, 5),
                    'fim' => substr($jornada->fim, 0, 5),
                ])
                ->values(),
            'bloqueios' => $dados['bloqueios']
                ->where('profissional_id', $profissionalId)
                ->filter(fn (Bloqueio $bloqueio) => $bloqueio->sobrepoe($dia, $fimDoDia))
                ->map(fn (Bloqueio $bloqueio) => [
                    'id' => $bloqueio->id,
                    'inicio' => $this->horaNoDia($bloqueio->inicio, $dia),
                    'fim' => $this->horaNoDia($bloqueio->fim, $dia),
                    'motivo' => $bloqueio->motivo,
                ])
                ->values(),
            'agendamentos' => $dados['agendamentos']
                ->where('profissional_id', $profissionalId)
                ->filter(fn (Agendamento $agendamento) => $agendamento->sobrepoe($dia, $fimDoDia))
                ->map(fn (Agendamento $agendamento) => [
                    'id' => $agendamento->id,
                    'inicio' => $this->horaNoDia($agendamento->inicio, $dia),
                    'fim' => $this->horaNoDia($agendamento->fim, $dia),
                    'status' => ['valor' => $agendamento->status->value, 'rotulo' => $agendamento->status->rotulo()],
                    'servico' => $agendamento->servico->nome,
                    'cliente' => [
                        'nome' => $agendamento->cliente->nome,
                        'telefone' => $agendamento->cliente->telefone,
                        'email' => $agendamento->cliente->email,
                    ],
                    'valor_centavos' => $agendamento->valor_centavos,
                    'sinal_centavos' => $agendamento->sinal_centavos,
                    'expira_em' => $agendamento->expira_em?->format('d/m H:i'),
                    'sinal_pago' => $agendamento->pagamento?->status === StatusPagamento::Aprovado,
                    'pode_cancelar' => $agendamento->podeSerCancelado(),
                ])
                ->values(),
        ];
    }

    /** Hora dentro do dia da coluna, presa entre 00:00 e 24:00. */
    private function horaNoDia(CarbonImmutable $momento, CarbonImmutable $dia): string
    {
        if ($momento <= $dia) {
            return '00:00';
        }

        if ($momento >= $dia->addDay()) {
            return '24:00';
        }

        return $momento->format('H:i');
    }

    /**
     * Faixa de horas exibida: cobre todas as jornadas e agendamentos das
     * colunas, com um padrão de 8h às 18h quando não há nada.
     *
     * @param  Collection<int, array<string, mixed>>  $colunas
     * @return array{colunas: list<array<string, mixed>>, hora_inicial: int, hora_final: int}
     */
    private function resultado(Collection $colunas): array
    {
        $horarios = $colunas->flatMap(fn (array $coluna) => collect($coluna['jornadas'])
            ->merge($coluna['agendamentos'])
            ->flatMap(fn (array $item) => [$item['inicio'], $item['fim']]));

        $horaInicial = $horarios->isEmpty()
            ? self::HORA_INICIAL_PADRAO
            : (int) min(self::HORA_INICIAL_PADRAO, ...$horarios->map(fn (string $hora) => (int) substr($hora, 0, 2)));

        $horaFinal = $horarios->isEmpty()
            ? self::HORA_FINAL_PADRAO
            : (int) max(self::HORA_FINAL_PADRAO, ...$horarios->map(fn (string $hora) => (int) ceil(((int) substr($hora, 0, 2) * 60 + (int) substr($hora, 3, 2)) / 60)));

        return [
            'colunas' => $colunas->values()->all(),
            'hora_inicial' => $horaInicial,
            'hora_final' => min(24, $horaFinal),
        ];
    }
}

import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';
import type {
    AgendamentoNaGrade,
    ColunaAgenda,
    FaixaHorario,
} from '@/types/painel';

// Altura de um minuto na tela: 1 hora = 72px, o bastante para ler um
// atendimento de 30 minutos.
const PX_POR_MINUTO = 1.2;

function minutos(hora: string): number {
    const [h, m] = hora.split(':').map(Number);

    return h * 60 + m;
}

/** Posição vertical de uma faixa dentro da grade, em pixels. */
function posicao(faixa: FaixaHorario, minutoInicial: number) {
    return {
        top: (minutos(faixa.inicio) - minutoInicial) * PX_POR_MINUTO,
        height: (minutos(faixa.fim) - minutos(faixa.inicio)) * PX_POR_MINUTO,
    };
}

export const CORES_STATUS: Record<string, string> = {
    pendente:
        'border-amber-500 bg-amber-50 text-amber-950 dark:bg-amber-950/60 dark:text-amber-100',
    confirmado:
        'border-emerald-600 bg-emerald-50 text-emerald-950 dark:bg-emerald-950/60 dark:text-emerald-100',
    concluido:
        'border-slate-400 bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
};

function useMinutoAtual(): number {
    const [agora, setAgora] = useState(() => new Date());

    useEffect(() => {
        const intervalo = setInterval(() => setAgora(new Date()), 60_000);

        return () => clearInterval(intervalo);
    }, []);

    return agora.getHours() * 60 + agora.getMinutes();
}

export function GradeAgenda({
    colunas,
    horaInicial,
    horaFinal,
    hoje,
    aoSelecionar,
}: {
    colunas: ColunaAgenda[];
    horaInicial: number;
    horaFinal: number;
    hoje: string;
    aoSelecionar: (agendamento: AgendamentoNaGrade) => void;
}) {
    const minutoInicial = horaInicial * 60;
    const altura = (horaFinal - horaInicial) * 60 * PX_POR_MINUTO;
    const horas = Array.from(
        { length: horaFinal - horaInicial },
        (_, i) => horaInicial + i,
    );
    const minutoAtual = useMinutoAtual();

    if (colunas.length === 0) {
        return (
            <div className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                Nenhum profissional ativo. Cadastre um em Profissionais.
            </div>
        );
    }

    return (
        <div className="overflow-x-auto rounded-xl border">
            <div
                className="grid min-w-fit"
                style={{
                    gridTemplateColumns: `3.5rem repeat(${colunas.length}, minmax(8.5rem, 1fr))`,
                }}
            >
                {/* Cabeçalho */}
                <div className="sticky left-0 z-20 border-b bg-background" />
                {colunas.map((coluna) => (
                    <div
                        key={coluna.chave}
                        className={cn(
                            'border-b border-l px-3 py-2',
                            coluna.data === hoje && 'bg-primary/5',
                        )}
                    >
                        <div className="truncate text-sm font-medium">
                            {coluna.titulo}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {coluna.subtitulo ??
                                `${coluna.agendamentos.length} agendamento(s)`}
                        </div>
                    </div>
                ))}

                {/* Eixo de horas */}
                <div
                    className="sticky left-0 z-20 bg-background"
                    style={{ height: altura }}
                >
                    {horas.map((hora) => (
                        <div
                            key={hora}
                            className="absolute right-2 -translate-y-1/2 text-xs text-muted-foreground tabular-nums"
                            style={{
                                top:
                                    (hora * 60 - minutoInicial) * PX_POR_MINUTO,
                            }}
                        >
                            {hora > horaInicial &&
                                `${String(hora).padStart(2, '0')}:00`}
                        </div>
                    ))}
                </div>

                {/* Colunas */}
                {colunas.map((coluna) => (
                    <div
                        key={coluna.chave}
                        className="relative border-l bg-muted/60"
                        style={{ height: altura }}
                    >
                        {/* Expediente: fora da jornada fica acinzentado. */}
                        {coluna.jornadas.map((jornada) => (
                            <div
                                key={`${jornada.inicio}-${jornada.fim}`}
                                className="absolute inset-x-0 bg-background"
                                style={posicao(jornada, minutoInicial)}
                            />
                        ))}

                        {horas.map((hora) => (
                            <div key={hora}>
                                <div
                                    className="absolute inset-x-0 border-t"
                                    style={{
                                        top:
                                            (hora * 60 - minutoInicial) *
                                            PX_POR_MINUTO,
                                    }}
                                />
                                <div
                                    className="absolute inset-x-0 border-t border-dashed border-border/50"
                                    style={{
                                        top:
                                            (hora * 60 + 30 - minutoInicial) *
                                            PX_POR_MINUTO,
                                    }}
                                />
                            </div>
                        ))}

                        {coluna.bloqueios.map((bloqueio) => (
                            <div
                                key={bloqueio.id}
                                className="absolute inset-x-0 overflow-hidden bg-[repeating-linear-gradient(135deg,transparent,transparent_6px,var(--color-border)_6px,var(--color-border)_12px)] px-2 py-1"
                                style={posicao(bloqueio, minutoInicial)}
                                title={bloqueio.motivo ?? 'Bloqueio'}
                            >
                                <span className="rounded bg-background/90 px-1 text-xs text-muted-foreground">
                                    {bloqueio.motivo ?? 'Bloqueio'}
                                </span>
                            </div>
                        ))}

                        {coluna.agendamentos.map((agendamento) => (
                            <button
                                key={agendamento.id}
                                type="button"
                                onClick={() => aoSelecionar(agendamento)}
                                className={cn(
                                    'absolute inset-x-1 overflow-hidden rounded-md border-l-4 px-2 py-1 text-left shadow-sm transition hover:z-10 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    CORES_STATUS[agendamento.status.valor],
                                )}
                                style={posicao(agendamento, minutoInicial)}
                            >
                                <div className="text-xs font-semibold tabular-nums">
                                    {agendamento.inicio}–{agendamento.fim}
                                </div>
                                <div className="truncate text-xs">
                                    {agendamento.cliente.nome}
                                </div>
                                <div className="truncate text-xs opacity-75">
                                    {agendamento.servico}
                                </div>
                            </button>
                        ))}

                        {coluna.data === hoje &&
                            minutoAtual >= minutoInicial &&
                            minutoAtual <= horaFinal * 60 && (
                                <div
                                    className="pointer-events-none absolute inset-x-0 z-10 border-t-2 border-red-500"
                                    style={{
                                        top:
                                            (minutoAtual - minutoInicial) *
                                            PX_POR_MINUTO,
                                    }}
                                >
                                    <div className="-mt-[5px] -ml-[5px] size-2 rounded-full bg-red-500" />
                                </div>
                            )}
                    </div>
                ))}
            </div>
        </div>
    );
}

export function LegendaAgenda() {
    const itens = [
        { rotulo: 'Confirmado', classe: CORES_STATUS.confirmado },
        { rotulo: 'Aguardando sinal', classe: CORES_STATUS.pendente },
        { rotulo: 'Concluído', classe: CORES_STATUS.concluido },
    ];

    return (
        <div className="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
            {itens.map((item) => (
                <span key={item.rotulo} className="flex items-center gap-1.5">
                    <span
                        className={cn(
                            'size-3 rounded-sm border-l-4',
                            item.classe,
                        )}
                    />
                    {item.rotulo}
                </span>
            ))}
            <span className="flex items-center gap-1.5">
                <span className="size-3 rounded-sm bg-[repeating-linear-gradient(135deg,transparent,transparent_2px,var(--color-border)_2px,var(--color-border)_4px)] ring-1 ring-border" />
                Bloqueio
            </span>
            <span className="flex items-center gap-1.5">
                <span className="size-3 rounded-sm bg-muted ring-1 ring-border" />
                Fora do expediente
            </span>
        </div>
    );
}

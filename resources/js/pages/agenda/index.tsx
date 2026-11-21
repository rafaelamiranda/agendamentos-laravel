import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import {
    CORES_STATUS,
    GradeAgenda,
    LegendaAgenda,
} from '@/components/agenda/grade-agenda';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import { agenda } from '@/routes';
import type {
    AgendamentoNaGrade,
    ColunaAgenda,
    ProfissionalResumo,
} from '@/types/painel';
import { formatarReais } from '@/types/painel';

type Visao = 'dia' | 'semana';

type Props = {
    colunas: ColunaAgenda[];
    hora_inicial: number;
    hora_final: number;
    visao: Visao;
    data: string;
    titulo: string;
    anterior: string;
    proxima: string;
    hoje: string;
    profissionais: ProfissionalResumo[];
    profissional_id: number | null;
};

export default function Agenda(props: Props) {
    const [selecionado, setSelecionado] = useState<AgendamentoNaGrade | null>(
        null,
    );

    // Mantém visão e profissional ao navegar; só troca o que mudou.
    const irPara = (
        mudancas: Partial<{ data: string; visao: Visao; profissional: number }>,
    ) =>
        agenda({
            query: {
                data: props.data,
                visao: props.visao,
                profissional: props.profissional_id ?? undefined,
                ...mudancas,
            },
        });

    return (
        <>
            <Head title="Agenda" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading title="Agenda" description={props.titulo} />

                    <div className="flex flex-wrap items-center gap-2">
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={props.visao}
                            onValueChange={(visao) =>
                                visao &&
                                router.visit(irPara({ visao: visao as Visao }))
                            }
                        >
                            <ToggleGroupItem value="dia" className="px-3">
                                Dia
                            </ToggleGroupItem>
                            <ToggleGroupItem value="semana" className="px-3">
                                Semana
                            </ToggleGroupItem>
                        </ToggleGroup>

                        {props.visao === 'semana' &&
                            props.profissionais.length > 0 && (
                                <Select
                                    value={String(props.profissional_id)}
                                    onValueChange={(id) =>
                                        router.visit(
                                            irPara({
                                                profissional: Number(id),
                                            }),
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        className="w-48"
                                        aria-label="Profissional"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {props.profissionais.map((p) => (
                                            <SelectItem
                                                key={p.id}
                                                value={String(p.id)}
                                            >
                                                {p.nome}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}

                        <div className="flex items-center gap-1">
                            <Button variant="outline" size="icon" asChild>
                                <Link
                                    href={irPara({ data: props.anterior })}
                                    aria-label="Anterior"
                                    preserveScroll
                                >
                                    <ChevronLeft />
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link
                                    href={irPara({ data: props.hoje })}
                                    preserveScroll
                                >
                                    Hoje
                                </Link>
                            </Button>
                            <Button variant="outline" size="icon" asChild>
                                <Link
                                    href={irPara({ data: props.proxima })}
                                    aria-label="Próximo"
                                    preserveScroll
                                >
                                    <ChevronRight />
                                </Link>
                            </Button>
                        </div>

                        <Input
                            type="date"
                            className="w-40"
                            aria-label="Ir para a data"
                            value={props.data}
                            onChange={(evento) =>
                                evento.target.value &&
                                router.visit(
                                    irPara({ data: evento.target.value }),
                                )
                            }
                        />
                    </div>
                </div>

                <GradeAgenda
                    colunas={props.colunas}
                    horaInicial={props.hora_inicial}
                    horaFinal={props.hora_final}
                    hoje={props.hoje}
                    aoSelecionar={setSelecionado}
                />

                <LegendaAgenda />
            </div>

            <Dialog
                open={selecionado !== null}
                onOpenChange={(aberto) => !aberto && setSelecionado(null)}
            >
                <DialogContent>
                    {selecionado && (
                        <>
                            <DialogHeader>
                                <DialogTitle>{selecionado.servico}</DialogTitle>
                                <DialogDescription>
                                    {selecionado.inicio} às {selecionado.fim}
                                </DialogDescription>
                            </DialogHeader>

                            <dl className="grid grid-cols-[8rem_1fr] gap-x-4 gap-y-2 text-sm">
                                <dt className="text-muted-foreground">
                                    Status
                                </dt>
                                <dd>
                                    <Badge
                                        variant="outline"
                                        className={cn(
                                            'border-l-4',
                                            CORES_STATUS[
                                                selecionado.status.valor
                                            ],
                                        )}
                                    >
                                        {selecionado.status.rotulo}
                                    </Badge>
                                </dd>
                                <dt className="text-muted-foreground">
                                    Cliente
                                </dt>
                                <dd>{selecionado.cliente.nome}</dd>
                                <dt className="text-muted-foreground">
                                    Telefone
                                </dt>
                                <dd>
                                    <a
                                        className="underline"
                                        href={`tel:${selecionado.cliente.telefone.replace(/\D/g, '')}`}
                                    >
                                        {selecionado.cliente.telefone}
                                    </a>
                                </dd>
                                <dt className="text-muted-foreground">
                                    E-mail
                                </dt>
                                <dd className="truncate">
                                    {selecionado.cliente.email}
                                </dd>
                                <dt className="text-muted-foreground">Valor</dt>
                                <dd>
                                    {formatarReais(selecionado.valor_centavos)}
                                </dd>
                                <dt className="text-muted-foreground">Sinal</dt>
                                <dd>
                                    {formatarReais(selecionado.sinal_centavos)}
                                    {selecionado.status.valor === 'pendente' &&
                                        selecionado.expira_em &&
                                        ` · aguardando até ${selecionado.expira_em}`}
                                </dd>
                            </dl>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

Agenda.layout = {
    breadcrumbs: [{ title: 'Agenda', href: agenda() }],
};

import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import BloqueioController from '@/actions/App/Http/Controllers/Painel/BloqueioController';
import JornadaController from '@/actions/App/Http/Controllers/Painel/JornadaController';
import ProfissionalController from '@/actions/App/Http/Controllers/Painel/ProfissionalController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { ConfirmarExclusao } from '@/components/painel/confirmar-exclusao';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/profissionais';
import type {
    BloqueioDoProfissional,
    JornadaDoProfissional,
    ServicoOpcao,
} from '@/types/painel';
import { DIAS_DA_SEMANA } from '@/types/painel';

type ProfissionalEditavel = {
    id: number;
    nome: string;
    email: string;
    ativo: boolean;
    servicos: number[];
    jornadas: JornadaDoProfissional[];
    bloqueios: BloqueioDoProfissional[];
};

export default function FormularioProfissional({
    profissional,
    servicos,
}: {
    profissional: ProfissionalEditavel | null;
    servicos: ServicoOpcao[];
}) {
    const titulo = profissional ? profissional.nome : 'Novo profissional';

    return (
        <>
            <Head title={titulo} />

            <div className="flex max-w-4xl flex-col gap-6 p-4">
                <Heading
                    title={titulo}
                    description={
                        profissional
                            ? 'Dados, serviços, jornadas e bloqueios.'
                            : 'Depois de salvar, defina as jornadas de trabalho.'
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Dados</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...(profissional
                                ? ProfissionalController.update.form(
                                      profissional.id,
                                  )
                                : ProfissionalController.store.form())}
                            options={{ preserveScroll: true }}
                            className="grid gap-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="nome">Nome</Label>
                                            <Input
                                                id="nome"
                                                name="nome"
                                                defaultValue={
                                                    profissional?.nome
                                                }
                                                required
                                            />
                                            <InputError message={errors.nome} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="email">
                                                E-mail
                                            </Label>
                                            <Input
                                                id="email"
                                                name="email"
                                                type="email"
                                                defaultValue={
                                                    profissional?.email
                                                }
                                                required
                                            />
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>
                                    </div>

                                    <fieldset className="grid gap-3">
                                        <legend className="mb-2 text-sm font-medium">
                                            Serviços que realiza
                                        </legend>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            {servicos.map((servico) => (
                                                <div
                                                    key={servico.id}
                                                    className="flex items-center gap-2"
                                                >
                                                    <Checkbox
                                                        id={`servico-${servico.id}`}
                                                        name="servicos[]"
                                                        value={String(
                                                            servico.id,
                                                        )}
                                                        defaultChecked={profissional?.servicos.includes(
                                                            servico.id,
                                                        )}
                                                    />
                                                    <Label
                                                        htmlFor={`servico-${servico.id}`}
                                                        className="font-normal"
                                                    >
                                                        {servico.nome}
                                                        {!servico.ativo && (
                                                            <span className="text-muted-foreground">
                                                                {' '}
                                                                (inativo)
                                                            </span>
                                                        )}
                                                    </Label>
                                                </div>
                                            ))}
                                        </div>
                                        <InputError message={errors.servicos} />
                                    </fieldset>

                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="ativo"
                                            name="ativo"
                                            defaultChecked={
                                                profissional?.ativo ?? true
                                            }
                                        />
                                        <Label
                                            htmlFor="ativo"
                                            className="font-normal"
                                        >
                                            Ativo (aparece na agenda e recebe
                                            reservas)
                                        </Label>
                                    </div>

                                    <div className="flex gap-2">
                                        <Button disabled={processing}>
                                            Salvar
                                        </Button>
                                        <Button variant="secondary" asChild>
                                            <Link href={index()}>Voltar</Link>
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {profissional && (
                    <>
                        <Jornadas
                            profissionalId={profissional.id}
                            jornadas={profissional.jornadas}
                        />
                        <Bloqueios
                            profissionalId={profissional.id}
                            bloqueios={profissional.bloqueios}
                        />
                    </>
                )}
            </div>
        </>
    );
}

function Jornadas({
    profissionalId,
    jornadas,
}: {
    profissionalId: number;
    jornadas: JornadaDoProfissional[];
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between gap-4">
                <div className="space-y-1.5">
                    <CardTitle>Jornadas</CardTitle>
                    <CardDescription>
                        Horário de trabalho por dia da semana. Para o almoço,
                        use um bloqueio ou divida em duas jornadas.
                    </CardDescription>
                </div>
                <DialogoJornada profissionalId={profissionalId} />
            </CardHeader>
            <CardContent>
                {jornadas.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Sem jornadas: este profissional não aparece com horários
                        livres.
                    </p>
                ) : (
                    <ul className="divide-y">
                        {jornadas.map((jornada) => (
                            <li
                                key={jornada.id}
                                className="flex items-center justify-between py-2 text-sm"
                            >
                                <span>
                                    <span className="inline-block w-36 font-medium">
                                        {DIAS_DA_SEMANA[jornada.dia_semana]}
                                    </span>
                                    <span className="tabular-nums">
                                        {jornada.inicio} às {jornada.fim}
                                    </span>
                                </span>
                                <span>
                                    <DialogoJornada
                                        profissionalId={profissionalId}
                                        jornada={jornada}
                                    />
                                    <ConfirmarExclusao
                                        acao={JornadaController.destroy.form({
                                            profissional: profissionalId,
                                            jornada: jornada.id,
                                        })}
                                        titulo="Remover jornada?"
                                        descricao={`${DIAS_DA_SEMANA[jornada.dia_semana]}, ${jornada.inicio} às ${jornada.fim}. Agendamentos já feitos não são afetados.`}
                                    />
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function DialogoJornada({
    profissionalId,
    jornada,
}: {
    profissionalId: number;
    jornada?: JornadaDoProfissional;
}) {
    const [aberto, setAberto] = useState(false);

    return (
        <Dialog open={aberto} onOpenChange={setAberto}>
            <DialogTrigger asChild>
                {jornada ? (
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Editar jornada"
                    >
                        <Pencil />
                    </Button>
                ) : (
                    <Button variant="outline" size="sm">
                        <Plus /> Adicionar
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {jornada ? 'Editar jornada' : 'Nova jornada'}
                </DialogTitle>
                <Form
                    {...(jornada
                        ? JornadaController.update.form({
                              profissional: profissionalId,
                              jornada: jornada.id,
                          })
                        : JornadaController.store.form(profissionalId))}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setAberto(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="dia_semana">
                                    Dia da semana
                                </Label>
                                <Select
                                    name="dia_semana"
                                    defaultValue={String(
                                        jornada?.dia_semana ?? 1,
                                    )}
                                >
                                    <SelectTrigger id="dia_semana">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {DIAS_DA_SEMANA.map((dia, numero) => (
                                            <SelectItem
                                                key={dia}
                                                value={String(numero)}
                                            >
                                                {dia}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.dia_semana} />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="inicio">Início</Label>
                                    <Input
                                        id="inicio"
                                        name="inicio"
                                        type="time"
                                        defaultValue={
                                            jornada?.inicio ?? '09:00'
                                        }
                                        required
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="fim">Fim</Label>
                                    <Input
                                        id="fim"
                                        name="fim"
                                        type="time"
                                        defaultValue={jornada?.fim ?? '18:00'}
                                        required
                                    />
                                </div>
                            </div>
                            <InputError message={errors.inicio ?? errors.fim} />
                            <DialogFooter>
                                <Button disabled={processing}>Salvar</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function formatarDataHora(valor: string): string {
    const [data, hora] = valor.split('T');
    const [ano, mes, dia] = data.split('-');

    return `${dia}/${mes}/${ano} ${hora}`;
}

function Bloqueios({
    profissionalId,
    bloqueios,
}: {
    profissionalId: number;
    bloqueios: BloqueioDoProfissional[];
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between gap-4">
                <div className="space-y-1.5">
                    <CardTitle>Bloqueios</CardTitle>
                    <CardDescription>
                        Folgas, férias e intervalos. Só aparecem os que ainda
                        não terminaram.
                    </CardDescription>
                </div>
                <DialogoBloqueio profissionalId={profissionalId} />
            </CardHeader>
            <CardContent>
                {bloqueios.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Nenhum bloqueio futuro.
                    </p>
                ) : (
                    <ul className="divide-y">
                        {bloqueios.map((bloqueio) => (
                            <li
                                key={bloqueio.id}
                                className="flex items-center justify-between gap-4 py-2 text-sm"
                            >
                                <span>
                                    <span className="font-medium tabular-nums">
                                        {formatarDataHora(bloqueio.inicio)} →{' '}
                                        {formatarDataHora(bloqueio.fim)}
                                    </span>
                                    {bloqueio.motivo && (
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {bloqueio.motivo}
                                        </span>
                                    )}
                                </span>
                                <span className="whitespace-nowrap">
                                    <DialogoBloqueio
                                        profissionalId={profissionalId}
                                        bloqueio={bloqueio}
                                    />
                                    <ConfirmarExclusao
                                        acao={BloqueioController.destroy.form({
                                            profissional: profissionalId,
                                            bloqueio: bloqueio.id,
                                        })}
                                        titulo="Remover bloqueio?"
                                        descricao="O período volta a ficar disponível para reservas."
                                    />
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function DialogoBloqueio({
    profissionalId,
    bloqueio,
}: {
    profissionalId: number;
    bloqueio?: BloqueioDoProfissional;
}) {
    const [aberto, setAberto] = useState(false);

    return (
        <Dialog open={aberto} onOpenChange={setAberto}>
            <DialogTrigger asChild>
                {bloqueio ? (
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Editar bloqueio"
                    >
                        <Pencil />
                    </Button>
                ) : (
                    <Button variant="outline" size="sm">
                        <Plus /> Adicionar
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {bloqueio ? 'Editar bloqueio' : 'Novo bloqueio'}
                </DialogTitle>
                <Form
                    {...(bloqueio
                        ? BloqueioController.update.form({
                              profissional: profissionalId,
                              bloqueio: bloqueio.id,
                          })
                        : BloqueioController.store.form(profissionalId))}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setAberto(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="bloqueio-inicio">
                                        Início
                                    </Label>
                                    <Input
                                        id="bloqueio-inicio"
                                        name="inicio"
                                        type="datetime-local"
                                        defaultValue={bloqueio?.inicio}
                                        required
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="bloqueio-fim">Fim</Label>
                                    <Input
                                        id="bloqueio-fim"
                                        name="fim"
                                        type="datetime-local"
                                        defaultValue={bloqueio?.fim}
                                        required
                                    />
                                </div>
                            </div>
                            <InputError message={errors.inicio ?? errors.fim} />
                            <div className="grid gap-2">
                                <Label htmlFor="motivo">Motivo</Label>
                                <Input
                                    id="motivo"
                                    name="motivo"
                                    defaultValue={bloqueio?.motivo ?? ''}
                                    placeholder="Férias, almoço, curso..."
                                />
                                <InputError message={errors.motivo} />
                            </div>
                            <DialogFooter>
                                <Button disabled={processing}>Salvar</Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

FormularioProfissional.layout = {
    breadcrumbs: [{ title: 'Profissionais', href: index() }],
};

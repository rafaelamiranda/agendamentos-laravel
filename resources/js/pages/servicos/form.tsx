import { Form, Head, Link } from '@inertiajs/react';
import ServicoController from '@/actions/App/Http/Controllers/Painel/ServicoController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/servicos';

type ServicoEditavel = {
    id: number;
    nome: string;
    descricao: string | null;
    duracao_minutos: number;
    preco: string; // "120,00"
    sinal: string;
    ativo: boolean;
};

export default function FormularioServico({
    servico,
}: {
    servico: ServicoEditavel | null;
}) {
    const titulo = servico ? servico.nome : 'Novo serviço';

    return (
        <>
            <Head title={titulo} />

            <div className="flex max-w-2xl flex-col gap-6 p-4">
                <Heading
                    title={titulo}
                    description="Mudar preço ou sinal vale para as próximas reservas; as já feitas mantêm o valor da época."
                />

                <Card>
                    <CardContent>
                        <Form
                            {...(servico
                                ? ServicoController.update.form(servico.id)
                                : ServicoController.store.form())}
                            className="grid gap-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="nome">Nome</Label>
                                        <Input
                                            id="nome"
                                            name="nome"
                                            defaultValue={servico?.nome}
                                            required
                                        />
                                        <InputError message={errors.nome} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="descricao">
                                            Descrição
                                        </Label>
                                        <textarea
                                            id="descricao"
                                            name="descricao"
                                            rows={3}
                                            defaultValue={
                                                servico?.descricao ?? ''
                                            }
                                            className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        />
                                        <InputError
                                            message={errors.descricao}
                                        />
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <div className="grid gap-2">
                                            <Label htmlFor="duracao_minutos">
                                                Duração (min)
                                            </Label>
                                            <Input
                                                id="duracao_minutos"
                                                name="duracao_minutos"
                                                type="number"
                                                min={5}
                                                max={480}
                                                step={5}
                                                defaultValue={
                                                    servico?.duracao_minutos ??
                                                    60
                                                }
                                                required
                                            />
                                            <InputError
                                                message={errors.duracao_minutos}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="preco">
                                                Preço (R$)
                                            </Label>
                                            <Input
                                                id="preco"
                                                name="preco"
                                                inputMode="decimal"
                                                placeholder="120,00"
                                                defaultValue={servico?.preco}
                                                required
                                            />
                                            <InputError
                                                message={errors.preco}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="sinal">
                                                Sinal (R$)
                                            </Label>
                                            <Input
                                                id="sinal"
                                                name="sinal"
                                                inputMode="decimal"
                                                placeholder="30,00"
                                                defaultValue={servico?.sinal}
                                                required
                                            />
                                            <InputError
                                                message={errors.sinal}
                                            />
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="ativo"
                                            name="ativo"
                                            defaultChecked={
                                                servico?.ativo ?? true
                                            }
                                        />
                                        <Label
                                            htmlFor="ativo"
                                            className="font-normal"
                                        >
                                            Ativo (disponível para reserva)
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
            </div>
        </>
    );
}

FormularioServico.layout = {
    breadcrumbs: [{ title: 'Serviços', href: index() }],
};

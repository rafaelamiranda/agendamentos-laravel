import { Form, Head } from '@inertiajs/react';
import { CircleCheck, RotateCw } from 'lucide-react';
import EstornoController from '@/actions/App/Http/Controllers/Painel/EstornoController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/estornos';

type EstornoPendente = {
    id: number;
    valor: string;
    gateway_id: string;
    desde: string;
    cliente: { nome: string; email: string };
    servico: string;
    profissional: string;
    horario: string;
    situacao: string;
};

export default function Estornos({
    estornos,
}: {
    estornos: EstornoPendente[];
}) {
    return (
        <>
            <Head title="Estornos" />

            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Estornos pendentes"
                    description="Sinais que precisam voltar para o cliente. O estorno é automático; o que fica aqui por muito tempo falhou nas tentativas e precisa de atenção."
                />

                {estornos.length === 0 ? (
                    <div className="flex items-center gap-3 rounded-xl border border-dashed p-10 text-sm text-muted-foreground">
                        <CircleCheck className="size-5 text-emerald-600" />
                        Nenhum estorno pendente.
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Cliente
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Atendimento
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Situação
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Valor
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Pendente desde
                                    </th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {estornos.map((estorno) => (
                                    <tr
                                        key={estorno.id}
                                        className="border-t align-top"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {estorno.cliente.nome}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {estorno.cliente.email}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div>
                                                {estorno.servico} com{' '}
                                                {estorno.profissional}
                                            </div>
                                            <div className="text-muted-foreground tabular-nums">
                                                {estorno.horario}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {estorno.situacao}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {estorno.valor}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {estorno.desde}
                                            <div className="text-xs text-muted-foreground">
                                                Mercado Pago #
                                                {estorno.gateway_id}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                <Form
                                                    {...EstornoController.tentarDeNovo.form(
                                                        estorno.id,
                                                    )}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            <RotateCw /> Tentar
                                                            de novo
                                                        </Button>
                                                    )}
                                                </Form>
                                                <Form
                                                    {...EstornoController.marcarComoDevolvido.form(
                                                        estorno.id,
                                                    )}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            size="sm"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Já devolvi
                                                        </Button>
                                                    )}
                                                </Form>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

Estornos.layout = {
    breadcrumbs: [{ title: 'Estornos', href: index() }],
};

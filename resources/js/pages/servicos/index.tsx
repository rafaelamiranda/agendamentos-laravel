import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import ServicoController from '@/actions/App/Http/Controllers/Painel/ServicoController';
import Heading from '@/components/heading';
import { ConfirmarExclusao } from '@/components/painel/confirmar-exclusao';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/servicos';

type ServicoNaLista = {
    id: number;
    nome: string;
    duracao_minutos: number;
    preco: string;
    sinal: string;
    ativo: boolean;
    profissionais_count: number;
};

export default function Servicos({ servicos }: { servicos: ServicoNaLista[] }) {
    return (
        <>
            <Head title="Serviços" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-end justify-between gap-4">
                    <Heading
                        title="Serviços"
                        description="Duração, preço e sinal cobrado na reserva."
                    />
                    <Button asChild>
                        <Link href={ServicoController.create()}>
                            <Plus /> Novo serviço
                        </Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Serviço
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Duração
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Preço
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Sinal
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Profissionais
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Situação
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {servicos.map((servico) => (
                                <tr key={servico.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {servico.nome}
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {servico.duracao_minutos} min
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        R$ {servico.preco}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        R$ {servico.sinal}
                                    </td>
                                    <td className="px-4 py-3">
                                        {servico.profissionais_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                servico.ativo
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {servico.ativo
                                                ? 'Ativo'
                                                : 'Inativo'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-right whitespace-nowrap">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            asChild
                                        >
                                            <Link
                                                href={ServicoController.edit(
                                                    servico.id,
                                                )}
                                                aria-label={`Editar ${servico.nome}`}
                                            >
                                                <Pencil />
                                            </Link>
                                        </Button>
                                        <ConfirmarExclusao
                                            acao={ServicoController.destroy.form(
                                                servico.id,
                                            )}
                                            titulo={`Excluir ${servico.nome}?`}
                                            descricao="Serviços com agendamentos não podem ser excluídos; nesse caso, desative o cadastro."
                                        />
                                    </td>
                                </tr>
                            ))}
                            {servicos.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Nenhum serviço cadastrado.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

Servicos.layout = {
    breadcrumbs: [{ title: 'Serviços', href: index() }],
};

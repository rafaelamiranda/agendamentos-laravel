import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import ProfissionalController from '@/actions/App/Http/Controllers/Painel/ProfissionalController';
import Heading from '@/components/heading';
import { ConfirmarExclusao } from '@/components/painel/confirmar-exclusao';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/profissionais';

type ProfissionalNaLista = {
    id: number;
    nome: string;
    email: string;
    ativo: boolean;
    servicos_count: number;
};

export default function Profissionais({
    profissionais,
}: {
    profissionais: ProfissionalNaLista[];
}) {
    return (
        <>
            <Head title="Profissionais" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-end justify-between gap-4">
                    <Heading
                        title="Profissionais"
                        description="Quem atende, quais serviços faz e em que horários."
                    />
                    <Button asChild>
                        <Link href={ProfissionalController.create()}>
                            <Plus /> Novo profissional
                        </Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Nome</th>
                                <th className="px-4 py-3 font-medium">
                                    E-mail
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Serviços
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Situação
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {profissionais.map((profissional) => (
                                <tr key={profissional.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {profissional.nome}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {profissional.email}
                                    </td>
                                    <td className="px-4 py-3">
                                        {profissional.servicos_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                profissional.ativo
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {profissional.ativo
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
                                                href={ProfissionalController.edit(
                                                    profissional.id,
                                                )}
                                                aria-label={`Editar ${profissional.nome}`}
                                            >
                                                <Pencil />
                                            </Link>
                                        </Button>
                                        <ConfirmarExclusao
                                            acao={ProfissionalController.destroy.form(
                                                profissional.id,
                                            )}
                                            titulo={`Excluir ${profissional.nome}?`}
                                            descricao="Jornadas e bloqueios também serão excluídos. Quem tem agendamentos não pode ser excluído; nesse caso, desative o cadastro."
                                        />
                                    </td>
                                </tr>
                            ))}
                            {profissionais.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Nenhum profissional cadastrado.
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

Profissionais.layout = {
    breadcrumbs: [{ title: 'Profissionais', href: index() }],
};

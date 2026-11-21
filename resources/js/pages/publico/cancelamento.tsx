import { Form, Head } from '@inertiajs/react';
import { CalendarX2, CircleCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    agendamento: {
        servico: string;
        profissional: string;
        quando: string;
        status: { valor: string; rotulo: string };
        sinal: string;
    };
    pode_cancelar: boolean;
    aviso_sinal: string;
    situacao_sinal: string | null;
    prazo_horas: number;
    acao: string; // URL assinada do POST
};

/**
 * Página aberta pelo link do e-mail. Mostra o agendamento e só cancela
 * quando a pessoa confirma (POST), nunca ao abrir o link.
 */
export default function Cancelamento({
    agendamento,
    pode_cancelar,
    aviso_sinal,
    situacao_sinal,
    prazo_horas,
    acao,
}: Props) {
    const cancelado = agendamento.status.valor === 'cancelado';

    return (
        <>
            <Head title="Cancelar agendamento" />

            <div className="space-y-6 text-sm">
                <div className="rounded-lg border p-4">
                    <div className="font-medium">{agendamento.servico}</div>
                    <div className="text-muted-foreground">
                        com {agendamento.profissional}
                    </div>
                    <div className="mt-2">{agendamento.quando}</div>
                </div>

                {cancelado && (
                    <div className="flex gap-3 rounded-lg border border-emerald-600/30 bg-emerald-50 p-4 text-emerald-950 dark:bg-emerald-950/40 dark:text-emerald-100">
                        <CircleCheck className="mt-0.5 size-5 shrink-0" />
                        <div>
                            <p className="font-medium">
                                Agendamento cancelado.
                            </p>
                            <p>{situacao_sinal}</p>
                        </div>
                    </div>
                )}

                {!cancelado && !pode_cancelar && (
                    <p className="flex gap-3 rounded-lg border p-4">
                        <CalendarX2 className="mt-0.5 size-5 shrink-0" />
                        Este agendamento está como "
                        {agendamento.status.rotulo.toLowerCase()}" e não pode
                        mais ser cancelado por aqui.
                    </p>
                )}

                {pode_cancelar && (
                    <Form action={acao} method="post" className="space-y-4">
                        {({ processing }) => (
                            <>
                                <p>
                                    <span className="font-medium">
                                        Sinal ({agendamento.sinal}):
                                    </span>{' '}
                                    {aviso_sinal}
                                </p>
                                <p className="text-muted-foreground">
                                    Política: cancelando com pelo menos{' '}
                                    {prazo_horas} horas de antecedência, o sinal
                                    é devolvido.
                                </p>
                                <Button
                                    variant="destructive"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    Confirmar cancelamento
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

Cancelamento.layout = {
    title: 'Cancelar agendamento',
    description: 'Confira os dados antes de confirmar.',
};

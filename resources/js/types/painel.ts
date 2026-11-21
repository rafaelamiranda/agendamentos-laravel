// Tipos das props que os controllers do painel enviam para as páginas.

export type Status = {
    valor: 'pendente' | 'confirmado' | 'concluido' | 'cancelado' | 'expirado';
    rotulo: string;
};

export type FaixaHorario = {
    inicio: string; // "HH:MM"
    fim: string;
};

export type AgendamentoNaGrade = FaixaHorario & {
    id: number;
    status: Status;
    servico: string;
    cliente: { nome: string; telefone: string; email: string };
    valor_centavos: number;
    sinal_centavos: number;
    expira_em: string | null;
};

export type BloqueioNaGrade = FaixaHorario & {
    id: number;
    motivo: string | null;
};

export type ColunaAgenda = {
    chave: string;
    titulo: string;
    subtitulo: string | null;
    data: string; // "AAAA-MM-DD"
    profissional_id: number;
    jornadas: FaixaHorario[];
    bloqueios: BloqueioNaGrade[];
    agendamentos: AgendamentoNaGrade[];
};

export type ProfissionalResumo = { id: number; nome: string };

export type ServicoOpcao = { id: number; nome: string; ativo: boolean };

export type JornadaDoProfissional = FaixaHorario & {
    id: number;
    dia_semana: number;
};

export type BloqueioDoProfissional = {
    id: number;
    inicio: string; // "AAAA-MM-DDTHH:MM" (input datetime-local)
    fim: string;
    motivo: string | null;
};

export const DIAS_DA_SEMANA = [
    'Domingo',
    'Segunda-feira',
    'Terça-feira',
    'Quarta-feira',
    'Quinta-feira',
    'Sexta-feira',
    'Sábado',
] as const;

export function formatarReais(centavos: number): string {
    return (centavos / 100).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}

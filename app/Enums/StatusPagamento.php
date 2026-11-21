<?php

namespace App\Enums;

enum StatusPagamento: string
{
    case Pendente = 'pendente';

    case Aprovado = 'aprovado';

    case Recusado = 'recusado';

    case Cancelado = 'cancelado';

    case Estornado = 'estornado';

    public function rotulo(): string
    {
        return match ($this) {
            self::Pendente => 'Aguardando pagamento',
            self::Aprovado => 'Aprovado',
            self::Recusado => 'Recusado',
            self::Cancelado => 'Cancelado',
            self::Estornado => 'Estornado',
        };
    }

    /**
     * Transições permitidas. Webhooks podem chegar repetidos ou fora de
     * ordem: um "pendente" atrasado não pode desfazer um "aprovado".
     */
    public function podeMudarPara(self $novo): bool
    {
        return match ($this) {
            self::Pendente => $novo !== self::Pendente,
            self::Aprovado => $novo === self::Estornado,
            self::Recusado, self::Cancelado, self::Estornado => false,
        };
    }
}

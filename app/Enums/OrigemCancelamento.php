<?php

namespace App\Enums;

enum OrigemCancelamento: string
{
    case Cliente = 'cliente';

    case Estabelecimento = 'estabelecimento';
}

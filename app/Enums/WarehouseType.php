<?php

namespace App\Enums;

enum WarehouseType: string
{
    case Principal = 'principal';
    case Consignacion = 'consignacion';
    case Ysan = 'ysan';
    case Desvalorizado = 'desvalorizado';
    case Externo = 'externo';
}

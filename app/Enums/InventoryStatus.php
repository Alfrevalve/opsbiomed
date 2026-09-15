<?php

namespace App\Enums;

enum InventoryStatus: string
{
    case Apto = 'apto';
    case Reservado = 'reservado';
    case Bloqueado = 'bloqueado';
    case Cuarentena = 'cuarentena';
    case FallaPreventiva = 'falla_preventiva';
    case Vencido = 'vencido';
    case Desvalorizado = 'desvalorizado';
    case Observado = 'observado';
}

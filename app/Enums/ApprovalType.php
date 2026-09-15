<?php

namespace App\Enums;

enum ApprovalType: string
{
    case CostoCero = 'costo_cero';
    case Canje = 'canje';
    case CesionUso = 'cesion_uso';
    case Emergencia = 'emergencia';
    case ReservaCritica = 'reserva_critica';
    case LiberacionFalla = 'liberacion_falla';
    case DeudaVencida = 'deuda_vencida';
}

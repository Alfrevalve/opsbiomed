<?php

namespace App\Enums;

enum FailureMoment: string
{
    case AntesCirugia = 'antes_cirugia';
    case DuranteCirugia = 'durante_cirugia';
    case DespuesCirugia = 'despues_cirugia';
    case Mantenimiento = 'mantenimiento';
    case Inventario = 'inventario';

    public function label(): string
    {
        return match ($this) {
            self::AntesCirugia => 'Antes de cirugia',
            self::DuranteCirugia => 'Durante cirugia',
            self::DespuesCirugia => 'Despues de cirugia',
            self::Mantenimiento => 'Mantenimiento',
            self::Inventario => 'Inventario',
        };
    }
}

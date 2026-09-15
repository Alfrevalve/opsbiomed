<?php

namespace App\Enums;

enum FailureType: string
{
    case NoGira = 'no_gira';
    case Vibracion = 'vibracion';
    case NoEncaja = 'no_encaja';
    case Sobrecalentamiento = 'sobrecalentamiento';
    case Fractura = 'fractura';
    case Desgaste = 'desgaste';
    case FallaElectrica = 'falla_electrica';
    case FallaMecanica = 'falla_mecanica';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::NoGira => 'No gira',
            self::Vibracion => 'Vibracion',
            self::NoEncaja => 'No encaja',
            self::Sobrecalentamiento => 'Sobrecalentamiento',
            self::Fractura => 'Fractura',
            self::Desgaste => 'Desgaste',
            self::FallaElectrica => 'Falla electrica',
            self::FallaMecanica => 'Falla mecanica',
            self::Otro => 'Otro',
        };
    }
}

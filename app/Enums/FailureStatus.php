<?php

namespace App\Enums;

enum FailureStatus: string
{
    case Reportada = 'reportada';
    case Bloqueada = 'bloqueada';
    case EnRevision = 'en_revision';
    case PendienteRepuesto = 'pendiente_repuesto';
    case Liberada = 'liberada';
    case DadaDeBaja = 'dada_de_baja';
    case Cerrada = 'cerrada';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return array_combine(
            self::values(),
            array_map(fn (self $status): string => $status->label(), self::cases()),
        );
    }

    /** @return list<string> */
    public static function editableValues(): array
    {
        return [
            self::Reportada->value,
            self::Bloqueada->value,
            self::EnRevision->value,
            self::PendienteRepuesto->value,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Reportada => 'Reportada',
            self::Bloqueada => 'Bloqueada',
            self::EnRevision => 'En revision',
            self::PendienteRepuesto => 'Pendiente de repuesto',
            self::Liberada => 'Liberada',
            self::DadaDeBaja => 'Dada de baja',
            self::Cerrada => 'Cerrada',
        };
    }
}

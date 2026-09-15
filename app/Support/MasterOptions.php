<?php

namespace App\Support;

class MasterOptions
{
    public const INSTITUTION_TYPES = [
        'hospital_publico',
        'clinica_privada',
        'instituto',
        'centro_medico',
        'otro',
    ];

    public const COMMERCIAL_CONDITIONS = [
        'regular',
        'convenio',
        'cesion_uso',
        'licitacion',
        'costo_cero_autorizado',
        'suspendida',
    ];

    public const DEBT_STATUSES = ['al_dia', 'observada', 'vencida', 'bloqueada'];

    public const SPECIALTIES = [
        'neurocirugia',
        'columna',
        'otorrino',
        'cabeza_cuello',
        'traumatologia',
        'otro',
    ];

    public const COMMERCIAL_PROFILES = ['clave', 'frecuente', 'ocasional', 'nuevo', 'inactivo'];

    public const POTENTIALS = ['alto', 'medio', 'bajo'];

    public const PRICE_TYPES = ['lista', 'convenio', 'licitacion', 'especial', 'cesion_uso', 'costo_cero'];
}

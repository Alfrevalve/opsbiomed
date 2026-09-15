<?php

namespace App\Policies;

use App\Models\SurgeryCase;
use App\Models\User;

class SurgeryCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cases.view');
    }

    public function view(User $user, SurgeryCase $case): bool
    {
        return $user->can('cases.view');
    }

    public function create(User $user): bool
    {
        return $user->can('cases.create');
    }

    public function update(User $user, SurgeryCase $case): bool
    {
        return $user->can('cases.update');
    }

    public function transition(User $user, SurgeryCase $case): bool
    {
        return $user->can('cases.update') || $user->can('cases.close');
    }

    public function schedule(User $user, SurgeryCase $case): bool
    {
        return $user->can('schedule.manage');
    }

    public function prepare(User $user, SurgeryCase $case): bool
    {
        return ($user->can('cases.update') || $user->can('cases.close') || $user->can('reservations.create'))
            && $user->hasAnyRole([
                'Administrador',
                'Jefe de Linea',
                'Programador Quirurgico',
                'Instrumentista',
                'Almacen',
                'Direccion Tecnica',
            ]);
    }

    public function reserve(User $user, SurgeryCase $case): bool
    {
        return $user->can('reservations.create') && $case->status->allowsReservation();
    }

    public function close(User $user, SurgeryCase $case): bool
    {
        return $user->can('cases.close') && $user->hasAnyRole([
            'Administrador',
            'Jefe de Linea',
            'Instrumentista',
            'Almacen',
            'Direccion Tecnica',
        ]);
    }
}

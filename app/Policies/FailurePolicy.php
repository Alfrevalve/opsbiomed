<?php

namespace App\Policies;

use App\Models\Failure;
use App\Models\User;

class FailurePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('failures.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Failure $failure): bool
    {
        return $user->can('failures.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('failures.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Failure $failure): bool
    {
        return $user->can('failures.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Failure $failure): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Failure $failure): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Failure $failure): bool
    {
        return false;
    }

    public function release(User $user, Failure $failure): bool
    {
        return $user->can('failures.release')
            && $user->hasAnyRole(['Administrador', 'Direccion Tecnica']);
    }

    public function retire(User $user, Failure $failure): bool
    {
        return $user->can('failures.release')
            && $user->hasAnyRole(['Administrador', 'Direccion Tecnica']);
    }

    public function close(User $user, Failure $failure): bool
    {
        return $user->can('failures.close');
    }
}

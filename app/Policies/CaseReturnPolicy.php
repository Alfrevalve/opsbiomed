<?php

namespace App\Policies;

use App\Models\CaseReturn;
use App\Models\User;

class CaseReturnPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('returns.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CaseReturn $caseReturn): bool
    {
        return $user->can('returns.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CaseReturn $caseReturn): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CaseReturn $caseReturn): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CaseReturn $caseReturn): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CaseReturn $caseReturn): bool
    {
        return false;
    }

    public function inspect(User $user, CaseReturn $caseReturn): bool
    {
        return $user->can('returns.inspect');
    }

    public function release(User $user, CaseReturn $caseReturn): bool
    {
        return $user->can('returns.release')
            && $user->hasAnyRole(['Administrador', 'Direccion Tecnica']);
    }

    public function audit(User $user, CaseReturn $caseReturn): bool
    {
        return $user->can('returns.audit');
    }
}

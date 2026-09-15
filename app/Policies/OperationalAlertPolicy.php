<?php

namespace App\Policies;

use App\Models\OperationalAlert;
use App\Models\User;

class OperationalAlertPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('alerts.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, OperationalAlert $operationalAlert): bool
    {
        return $user->can('alerts.view');
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
    public function update(User $user, OperationalAlert $operationalAlert): bool
    {
        return $user->can('alerts.manage') || $user->can('alerts.resolve');
    }

    public function acknowledge(User $user, OperationalAlert $operationalAlert): bool
    {
        return $user->can('alerts.manage');
    }

    public function resolve(User $user, OperationalAlert $operationalAlert): bool
    {
        return $user->can('alerts.resolve');
    }

    public function dismiss(User $user, OperationalAlert $operationalAlert): bool
    {
        return $user->can('alerts.manage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, OperationalAlert $operationalAlert): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, OperationalAlert $operationalAlert): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, OperationalAlert $operationalAlert): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Models\DocumentEvidence;
use App\Models\User;

class DocumentEvidencePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('documents.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DocumentEvidence $documentEvidence): bool
    {
        return $documentEvidence->isVisibleTo($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('documents.upload');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DocumentEvidence $documentEvidence): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DocumentEvidence $documentEvidence): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DocumentEvidence $documentEvidence): bool
    {
        return false;
    }

    public function upload(User $user): bool
    {
        return $user->can('documents.upload');
    }

    public function validate(User $user, DocumentEvidence $documentEvidence): bool
    {
        return $user->can('documents.validate') && $documentEvidence->isVisibleTo($user);
    }

    public function delete(User $user, DocumentEvidence $documentEvidence): bool
    {
        return $user->can('documents.delete') && $documentEvidence->isVisibleTo($user);
    }
}

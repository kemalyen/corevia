<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\IntegrationEngine\Models\Ingestion;

class IngestionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-integration-runs');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Ingestion $ingestion): bool
    {
        return $this->viewAny($user);
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
    public function update(User $user, Ingestion $ingestion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Ingestion $ingestion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Ingestion $ingestion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Ingestion $ingestion): bool
    {
        return false;
    }

    public function retry(User $user, Ingestion $ingestion): bool
    {
        return $user->can('retry-integration-runs') && $ingestion->canRetry();
    }
}

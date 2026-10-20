<?php

namespace App\Policies;

use App\Models\SalesOrderLine;
use App\Models\User;

class SalesOrderLinePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-any-order');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SalesOrderLine $salesOrderLine): bool
    {
        return $user->can('view', $salesOrderLine->salesOrder);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('update-order');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SalesOrderLine $salesOrderLine): bool
    {
        return $user->can('update', $salesOrderLine->salesOrder);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SalesOrderLine $salesOrderLine): bool
    {
        return $user->can('update', $salesOrderLine->salesOrder);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('manage-all-orders') || $user->can('manage-all-accounts');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SalesOrderLine $salesOrderLine): bool
    {
        return $user->can('update', $salesOrderLine->salesOrder);
    }

    public function restoreAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SalesOrderLine $salesOrderLine): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}

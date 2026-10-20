<?php

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;

class SalesOrderPolicy
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
    public function view(User $user, SalesOrder $salesOrder): bool
    {
        return $user->can('view-order') && $this->canAccessOrder($user, $salesOrder);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create-order');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SalesOrder $salesOrder): bool
    {
        return $user->can('update-order') && $this->canAccessOrder($user, $salesOrder);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SalesOrder $salesOrder): bool
    {
        return $user->can('delete-order') && $this->canAccessOrder($user, $salesOrder);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete-order') && $user->can('manage-all-orders');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SalesOrder $salesOrder): bool
    {
        return $user->can('delete-order') && $this->canAccessOrder($user, $salesOrder);
    }

    public function restoreAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SalesOrder $salesOrder): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    private function canAccessOrder(User $user, SalesOrder $salesOrder): bool
    {
        return $user->can('manage-all-orders')
            || $user->can('manage-all-accounts')
            || $user->managesAccount($salesOrder->account)
            || $user->belongsToAccount($salesOrder->account);
    }
}

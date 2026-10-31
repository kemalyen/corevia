<?php

namespace App\Policies;

use App\Models\AccountProductPrice;
use App\Models\User;

class AccountProductPricePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('manage-account-catalogue');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AccountProductPrice $accountProductPrice): bool
    {
        return $user->can('manage-account-catalogue') && $this->canAccessAccount($user, $accountProductPrice);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('manage-account-catalogue');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AccountProductPrice $accountProductPrice): bool
    {
        return $this->view($user, $accountProductPrice);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AccountProductPrice $accountProductPrice): bool
    {
        return $this->view($user, $accountProductPrice);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AccountProductPrice $accountProductPrice): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AccountProductPrice $accountProductPrice): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('manage-account-catalogue');
    }

    private function canAccessAccount(User $user, AccountProductPrice $price): bool
    {
        return $user->can('manage-all-accounts')
            || $user->managesAccount($price->account)
            || $user->belongsToAccount($price->account);
    }
}

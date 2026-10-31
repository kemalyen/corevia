<?php

namespace App\Portal;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class VendorAccountContext
{
    /**
     * @throws AuthorizationException
     */
    public function resolve(?User $user = null): Account
    {
        $user ??= auth()->user();

        if (! $user instanceof User || ! $user->is_active || ! $user->hasRole('vendor-user')) {
            throw new AuthorizationException('Vendor portal access is not authorised.');
        }

        $account = Account::query()
            ->whereKey($user->account_id)
            ->where('account_type', AccountType::VENDOR)
            ->where('account_status', AccountStatus::ACTIVE)
            ->first();

        if ($account === null) {
            throw new AuthorizationException('An active Vendor account membership is required.');
        }

        return $account;
    }
}

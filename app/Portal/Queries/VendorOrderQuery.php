<?php

namespace App\Portal\Queries;

use App\Models\SalesOrder;
use App\Models\User;
use App\Portal\VendorAccountContext;
use Illuminate\Database\Eloquent\Builder;

class VendorOrderQuery
{
    public function __construct(private VendorAccountContext $accountContext) {}

    /** @return Builder<SalesOrder> */
    public function forUser(User $user): Builder
    {
        $account = $this->accountContext->resolve($user);

        return SalesOrder::query()->whereBelongsTo($account);
    }
}

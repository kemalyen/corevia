<?php

namespace App\Livewire\Portal;

use App\Models\User;
use App\Portal\Queries\VendorOrderQuery;
use App\Portal\VendorAccountContext;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::portal')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(VendorAccountContext $context, VendorOrderQuery $orders): View
    {
        /** @var User $user */
        $user = auth()->user();
        $account = $context->resolve($user);
        $query = $orders->forUser($user);
        $statusCounts = (clone $query)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $currencyTotals = (clone $query)
            ->selectRaw('currency, sum(total_amount) as aggregate')
            ->whereNotNull('currency')
            ->groupBy('currency')
            ->pluck('aggregate', 'currency');

        return view('livewire.portal.dashboard', [
            'account' => $account,
            'orderCount' => $query->count(),
            'statusCounts' => $statusCounts,
            'currencyTotals' => $currencyTotals,
            'recentOrders' => $orders->forUser($user)->latest('order_date')->limit(5)->get(),
        ]);
    }
}

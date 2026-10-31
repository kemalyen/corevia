<?php

namespace App\Livewire\Portal\Orders;

use App\Models\SalesOrder;
use App\Models\User;
use App\Portal\Queries\VendorOrderQuery;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::portal')]
#[Title('Order details')]
class Show extends Component
{
    #[Locked]
    public int $salesOrderId;

    public function mount(SalesOrder $salesOrder, VendorOrderQuery $orders): void
    {
        /** @var User $user */
        $user = auth()->user();
        $this->salesOrderId = $orders->forUser($user)->whereKey($salesOrder->getKey())->value('id') ?? abort(404);
    }

    public function render(VendorOrderQuery $orders): View
    {
        /** @var User $user */
        $user = auth()->user();
        $order = $orders->forUser($user)
            ->with('lines.product')
            ->findOrFail($this->salesOrderId);

        return view('livewire.portal.orders.show', ['order' => $order]);
    }
}

<?php

namespace App\Livewire\Portal\Orders;

use App\Enums\OrderStatus;
use App\Models\User;
use App\Portal\Queries\VendorOrderQuery;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::portal')]
#[Title('Orders')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $from = '';

    public string $to = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(VendorOrderQuery $orders): View
    {
        $this->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        /** @var User $user */
        $user = auth()->user();
        $search = trim($this->search);
        $results = $orders->forUser($user)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_reference', 'like', "%{$search}%");
            }))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->from !== '', fn ($query) => $query->whereDate('order_date', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('order_date', '<=', $this->to))
            ->latest('order_date')
            ->paginate(15);

        return view('livewire.portal.orders.index', [
            'orders' => $results,
            'statuses' => OrderStatus::cases(),
        ]);
    }
}

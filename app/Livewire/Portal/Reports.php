<?php

namespace App\Livewire\Portal;

use App\Models\User;
use App\Portal\Queries\VendorOrderQuery;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::portal')]
#[Title('Reports')]
class Reports extends Component
{
    public string $from = '';

    public string $to = '';

    public function render(VendorOrderQuery $orders): View
    {
        $this->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        /** @var User $user */
        $user = auth()->user();
        $filtered = fn () => $orders->forUser($user)
            ->when($this->from !== '', fn ($query) => $query->whereDate('order_date', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('order_date', '<=', $this->to));
        $statusRows = $filtered()->selectRaw('status, count(*) as order_count')->groupBy('status')->orderBy('status')->get();
        $currencyRows = $filtered()->selectRaw('currency, sum(total_amount) as total_value')->whereNotNull('currency')->groupBy('currency')->orderBy('currency')->get();
        $productRows = $filtered()
            ->join('sales_order_lines', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->selectRaw("sales_order_lines.sku, sales_order_lines.description, coalesce(sales_order_lines.unit_of_measure, 'Not supplied') as unit, sum(sales_order_lines.quantity) as ordered_quantity")
            ->groupBy('sales_order_lines.sku', 'sales_order_lines.description', 'sales_order_lines.unit_of_measure')
            ->orderByDesc('ordered_quantity')
            ->limit(25)
            ->get();

        return view('livewire.portal.reports', compact('statusRows', 'currencyRows', 'productRows'));
    }
}

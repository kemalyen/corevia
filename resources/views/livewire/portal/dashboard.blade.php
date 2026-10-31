<div class="grid gap-8">
    <header><p class="text-sm font-medium text-cyan-700">{{ $account->account_number }}</p><h1 class="mt-1 text-3xl font-semibold tracking-tight">Welcome back, {{ auth()->user()->name }}</h1><p class="mt-2 text-slate-500">A current view of submitted orders for {{ $account->name }}.</p></header>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Submitted orders</p><p class="mt-2 text-3xl font-semibold">{{ number_format($orderCount) }}</p></div>
        @foreach ($statusCounts->take(3) as $status => $count)
            <div wire:key="status-{{ $status }}" class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm capitalize text-slate-500">{{ $status }}</p><p class="mt-2 text-3xl font-semibold">{{ number_format($count) }}</p></div>
        @endforeach
    </section>
    <section class="grid gap-6 xl:grid-cols-[1fr_2fr]">
        <div class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="font-semibold">Order value by currency</h2><div class="mt-5 grid gap-4">@forelse ($currencyTotals as $currency => $total)<div wire:key="currency-{{ $currency }}" class="flex items-center justify-between"><span class="text-sm text-slate-500">{{ $currency }}</span><span class="font-semibold">{{ number_format((float) $total, 2) }}</span></div>@empty<p class="text-sm text-slate-500">No submitted order value yet.</p>@endforelse</div></div>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white"><div class="flex items-center justify-between border-b border-slate-200 px-6 py-5"><h2 class="font-semibold">Recent orders</h2><a href="{{ route('portal.orders.index') }}" wire:navigate class="text-sm font-medium text-cyan-700">View all</a></div><div class="divide-y divide-slate-100">@forelse ($recentOrders as $order)<a wire:key="recent-{{ $order->id }}" href="{{ route('portal.orders.show', $order) }}" wire:navigate class="flex items-center justify-between gap-4 px-6 py-4 hover:bg-slate-50"><div><p class="font-medium">{{ $order->order_number }}</p><p class="text-sm text-slate-500">{{ $order->order_date->format('d M Y') }}</p></div><div class="text-right"><p class="font-medium">{{ $order->currency }} {{ number_format((float) $order->total_amount, 2) }}</p><p class="text-sm capitalize text-slate-500">{{ $order->status->value }}</p></div></a>@empty<p class="px-6 py-10 text-center text-sm text-slate-500">No orders have been submitted.</p>@endforelse</div></div>
    </section>
</div>

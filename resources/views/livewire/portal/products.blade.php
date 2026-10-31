<div class="grid gap-6">
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Products & Stock</h1>
            <p class="mt-2 text-slate-500">Products and effective prices available to your active Vendor account.</p>
        </div><a href="{{ route('portal.orders.create') }}" wire:navigate class="rounded-xl bg-slate-950 px-4 py-3 text-center text-sm font-semibold text-white">Create order</a>
    </header>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Stock availability is <strong>Unknown</strong> because no vendor-safe stock feed is currently available. Nothing shown here guarantees fulfilment.</div>
    <label class="grid max-w-lg gap-2 text-sm font-medium">Search products<input wire:model.live.debounce.300ms="search" type="search" placeholder="Code, name or description" class="rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-cyan-600 focus:ring-3 focus:ring-cyan-100"></label>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-4">Code</th>
                        <th class="px-5 py-4">Description</th>
                        <th class="px-5 py-4">Unit</th>
                        <th class="px-5 py-4">Price</th>
                        <th class="px-5 py-4">Availability</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($products as $product)
                    <tr wire:key="product-{{ $product->id }}">
                        <td class="px-5 py-4 font-medium">{{ $product->sku }}</td>
                        <td class="px-5 py-4">
                            <p class="font-medium">{{ $product->name }}</p>
                            <p class="mt-1 max-w-xl text-slate-500">{{ $product->description ?: 'No description supplied.' }}</p>
                        </td>
                        <td class="px-5 py-4 text-slate-500">{{ $product->unit_of_measure ?: 'Not supplied' }}</td>
                        <td class="px-5 py-4">
                            @if (data_get($product, 'effective_price'))
                                <span class="font-medium">{{ data_get($product, 'effective_price.currency') }} {{ number_format((float) data_get($product, 'effective_price.amount'), 4) }}</span>
                                <span class="block text-xs text-slate-500">{{ str(data_get($product, 'effective_price.source'))->replace('_', ' ')->title() }}</span>
                            @else
                                <span class="text-slate-500">Unavailable</span>
                            @endif
                        </td>
                        <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">Unknown</span></td>
                    </tr>@empty<tr>
                        <td colspan="5" class="px-5 py-12 text-center text-slate-500">No products match your search.</td>
                    </tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $products->links() }}
</div>

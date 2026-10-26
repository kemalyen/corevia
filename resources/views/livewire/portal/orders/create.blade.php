<div class="grid gap-6">
    @if ($submittedOrder)
        <div class="mx-auto w-full max-w-2xl rounded-3xl border border-emerald-200 bg-white p-8 text-center shadow-sm">
            <div class="mx-auto grid size-14 place-items-center rounded-full bg-emerald-100 text-2xl text-emerald-700">✓</div>
            <h1 class="mt-5 text-3xl font-semibold tracking-tight">Order request submitted</h1>
            <p class="mt-2 text-slate-500">Your local reference is <strong class="text-slate-900">{{ $submittedOrder->order_number }}</strong>.</p>
            <p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">Awaiting ERP processing. This is not ERP acceptance and stock has not been reserved.</p>
            <a href="{{ route('portal.orders.show', $submittedOrder) }}" wire:navigate class="mt-6 inline-flex rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white">View order</a>
        </div>
    @else
        <header>
            <a href="{{ route('portal.orders.index') }}" wire:navigate class="text-sm font-medium text-cyan-700">← Back to orders</a>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight">Create order</h1>
            <p class="mt-2 text-slate-500">Prices and ownership are revalidated when you submit.</p>
        </header>

        <section
            x-data="{
                query: @js($selectedProductLabel ?? ''),
                selectedLabel: @js($selectedProductLabel ?? ''),
                results: [],
                open: false,
                loading: false,
                searched: false,
                searchError: false,
                hasMore: false,
                activeIndex: -1,
                requestSequence: 0,
                invalidateSelection() {
                    if (this.selectedLabel !== '' && this.query !== this.selectedLabel) {
                        this.selectedLabel = '';
                        $wire.clearProductSelection();
                    }
                },
                async search() {
                    const sequence = ++this.requestSequence;
                    const term = this.query.trim();

                    this.searchError = false;
                    this.hasMore = false;
                    this.searched = false;

                    if (term.length < 2) {
                        this.results = [];
                        this.open = false;
                        this.loading = false;
                        this.activeIndex = -1;

                        return;
                    }

                    this.loading = true;
                    this.open = true;

                    try {
                        const response = await $wire.searchProducts(term);

                        if (sequence !== this.requestSequence) {
                            return;
                        }

                        if (response.status === 'error') {
                            this.searchError = true;
                            this.results = [];
                            this.activeIndex = -1;

                            return;
                        }

                        this.results = response.results;
                        this.hasMore = response.has_more;
                        this.searched = true;
                        this.activeIndex = this.results.length > 0 ? 0 : -1;
                    } catch (error) {
                        if (sequence === this.requestSequence) {
                            this.searchError = true;
                            this.results = [];
                            this.activeIndex = -1;
                        }
                    } finally {
                        if (sequence === this.requestSequence) {
                            this.loading = false;
                        }
                    }
                },
                moveActive(direction) {
                    if (!this.open || this.results.length === 0) {
                        return;
                    }

                    this.activeIndex = (this.activeIndex + direction + this.results.length) % this.results.length;
                    this.$nextTick(() => document.getElementById(`product-option-${this.results[this.activeIndex].id}`)?.scrollIntoView({ block: 'nearest' }));
                },
                async choose(result) {
                    this.searchError = false;

                    try {
                        const selection = await $wire.selectProduct(result.id);

                        this.query = selection.label;
                        this.selectedLabel = selection.label;
                        this.results = [];
                        this.open = false;
                        this.searched = false;
                        this.hasMore = false;
                        this.activeIndex = -1;
                    } catch (error) {
                        this.searchError = true;
                    }
                },
                async clearSelection() {
                    ++this.requestSequence;
                    await $wire.clearProductSelection();
                    this.resetSelection();
                    this.$refs.productSearch.focus();
                },
                resetSelection() {
                    ++this.requestSequence;
                    this.query = '';
                    this.selectedLabel = '';
                    this.results = [];
                    this.open = false;
                    this.loading = false;
                    this.searched = false;
                    this.searchError = false;
                    this.hasMore = false;
                    this.activeIndex = -1;
                },
            }"
            @product-selection-cleared.window="resetSelection()"
            class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 md:grid-cols-[minmax(0,1fr)_10rem_auto] md:items-end"
        >
            <div class="relative grid gap-2 text-sm font-medium">
                <label id="product-search-label" for="product-search">Product</label>
                <div class="relative">
                    <input
                        id="product-search"
                        x-ref="productSearch"
                        x-model="query"
                        @input="invalidateSelection()"
                        @input.debounce.300ms="search()"
                        @focus="if (results.length > 0 || searched || searchError) open = true"
                        @keydown.arrow-down.prevent="moveActive(1)"
                        @keydown.arrow-up.prevent="moveActive(-1)"
                        @keydown.enter.prevent="if (open && activeIndex >= 0) choose(results[activeIndex])"
                        @keydown.escape="open = false"
                        @blur="setTimeout(() => open = false, 150)"
                        type="search"
                        role="combobox"
                        aria-labelledby="product-search-label"
                        aria-controls="product-search-results"
                        aria-autocomplete="list"
                        :aria-expanded="open.toString()"
                        :aria-activedescendant="open && activeIndex >= 0 ? `product-option-${results[activeIndex].id}` : null"
                        autocomplete="off"
                        placeholder="Search by product code or name"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 pr-20"
                    >
                    <span x-show="loading" x-cloak class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-normal text-slate-500">Searching…</span>
                    <button
                        x-show="selectedLabel !== '' && !loading"
                        x-cloak
                        type="button"
                        @mousedown.prevent
                        @click="clearSelection()"
                        aria-label="Clear selected product"
                        class="absolute inset-y-0 right-3 text-xs font-semibold text-slate-500 hover:text-slate-900"
                    >Clear</button>
                </div>

                <p x-show="query.trim().length > 0 && query.trim().length < 2" x-cloak class="text-xs font-normal text-slate-500">Enter at least 2 characters to search.</p>
                <p class="sr-only" role="status" aria-live="polite">
                    <span x-show="loading">Searching products.</span>
                    <span x-show="searched && !loading" x-text="`${results.length} products found.`"></span>
                    <span x-show="searchError">Product search failed.</span>
                </p>

                <div
                    id="product-search-results"
                    x-show="open"
                    x-cloak
                    role="listbox"
                    aria-labelledby="product-search-label"
                    class="absolute top-full z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl"
                >
                    <template x-for="(result, index) in results" :key="result.id">
                        <button
                            :id="`product-option-${result.id}`"
                            type="button"
                            role="option"
                            :aria-selected="(activeIndex === index).toString()"
                            @mouseenter="activeIndex = index"
                            @mousedown.prevent
                            @click="choose(result)"
                            class="block w-full rounded-lg px-3 py-2 text-left hover:bg-slate-100"
                            :class="activeIndex === index ? 'bg-slate-100' : ''"
                        >
                            <span class="block font-semibold text-slate-900" x-text="result.code"></span>
                            <span class="block font-normal text-slate-600" x-text="result.name"></span>
                        </button>
                    </template>

                    <p x-show="searched && results.length === 0 && !searchError" class="px-3 py-4 text-center text-sm font-normal text-slate-500">No matching products found.</p>
                    <p x-show="searchError" class="px-3 py-4 text-center text-sm font-normal text-red-600">Product search is unavailable. Please try again.</p>
                    <p x-show="hasMore" class="border-t border-slate-100 px-3 py-2 text-xs font-normal text-slate-500">Showing the first 20 results. Refine your search to find a specific product.</p>
                </div>

                @error('selectedProductId')
                    <span class="text-xs text-red-600">{{ $message }}</span>
                @enderror
            </div>

            <label class="grid gap-2 text-sm font-medium">
                Quantity
                <input wire:model="quantity" type="number" min="0.0001" max="999999.9999" step="0.0001" class="rounded-xl border border-slate-300 px-3 py-2.5">
                @error('quantity')
                    <span class="text-xs text-red-600">{{ $message }}</span>
                @enderror
            </label>

            <button type="button" wire:click="addLine" wire:loading.attr="disabled" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60">Add or update</button>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-6 py-5"><h2 class="font-semibold">Draft lines</h2></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-4">Product</th><th class="px-5 py-4">Quantity</th><th class="px-5 py-4 text-right">Price</th><th class="px-5 py-4 text-right">Total</th><th class="px-5 py-4"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($lines as $index => $line)
                            <tr wire:key="draft-line-{{ data_get($line, 'product_id', $index) }}">
                                <td class="px-5 py-4"><p class="font-medium">{{ data_get($line, 'sku', 'Pending validation') }}</p><p class="text-slate-500">{{ data_get($line, 'name', '') }}</p></td>
                                <td class="px-5 py-4">{{ data_get($line, 'quantity', '') }}</td>
                                <td class="px-5 py-4 text-right">{{ data_get($line, 'currency', '') }} {{ number_format((float) data_get($line, 'price', 0), 2) }}</td>
                                <td class="px-5 py-4 text-right font-medium">{{ data_get($line, 'currency', '') }} {{ number_format((float) data_get($line, 'total', 0), 2) }}</td>
                                <td class="px-5 py-4 text-right"><button type="button" wire:click="removeLine({{ $index }})" class="text-sm font-medium text-red-600">Remove</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Add a product to begin your draft.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @error('lines')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror

        <section class="grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 sm:grid-cols-[1fr_auto] sm:items-end">
            <label class="grid gap-2 text-sm font-medium">
                Your reference <span class="font-normal text-slate-500">Optional</span>
                <input wire:model="customerReference" type="text" maxlength="255" class="rounded-xl border border-slate-300 px-3 py-2.5">
                @error('customerReference')
                    <span class="text-xs text-red-600">{{ $message }}</span>
                @enderror
            </label>
            <button type="button" wire:click="submit" wire:confirm="Submit this order request? Prices and product access will be checked again. This does not reserve stock." wire:loading.attr="disabled" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60">
                <span wire:loading.remove wire:target="submit">Review & submit request</span>
                <span wire:loading wire:target="submit">Submitting…</span>
            </button>
        </section>
    @endif
</div>

<div class="grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)_22rem]">
    <aside class="rounded-2xl border border-slate-200 bg-white p-4 xl:sticky xl:top-24 xl:h-[calc(100vh-8rem)]">
        <button type="button" wire:click="newConversation" class="w-full rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">New conversation</button>
        <div class="mt-4 grid gap-1">
            @forelse ($conversations as $conversation)
                <button type="button" wire:key="conversation-{{ $conversation->id }}" wire:click="selectConversation({{ $conversation->id }})" class="rounded-xl px-3 py-2 text-left text-sm {{ $conversationId === $conversation->id ? 'bg-cyan-50 font-semibold text-cyan-900' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span class="block truncate">{{ $conversation->title ?: 'New conversation' }}</span>
                    <span class="text-xs font-normal text-slate-400">{{ $conversation->last_message_at?->diffForHumans() }}</span>
                </button>
            @empty
                <p class="px-3 py-6 text-center text-sm text-slate-500">No conversations yet.</p>
            @endforelse
        </div>
    </aside>

    <section class="flex min-h-[42rem] min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <header class="border-b border-slate-200 px-5 py-4">
            <h1 class="text-xl font-semibold">Vendor AI assistant</h1>
            <p class="mt-1 text-sm text-slate-500">Active account: <strong class="text-slate-700">{{ $account->name }}</strong>. Prices and access always come from the server.</p>
        </header>

        <div class="flex-1 space-y-4 overflow-y-auto p-5">
            @forelse ($messages as $chatMessage)
                <article wire:key="message-{{ $chatMessage->id }}" class="max-w-3xl rounded-2xl px-4 py-3 text-sm {{ $chatMessage->role === 'user' ? 'ml-auto bg-slate-950 text-white' : 'bg-slate-100 text-slate-800' }}">
                    <p class="whitespace-pre-wrap">{{ $chatMessage->content }}</p>
                    @foreach (data_get($chatMessage->metadata, 'tool_results', []) as $toolResult)
                        <div class="mt-3 rounded-xl border border-slate-200 bg-white p-3 text-slate-800">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ str(data_get($toolResult, 'name', 'result'))->replace('_', ' ')->title() }}</p>
                            @if (is_array(data_get($toolResult, 'result.lines')))
                                <div class="mt-2 grid gap-2">
                                    @forelse (data_get($toolResult, 'result.lines', []) as $line)
                                        <div class="flex justify-between gap-3"><span>{{ data_get($line, 'sku') }} · {{ data_get($line, 'name') }}</span><span class="font-medium">{{ data_get($line, 'quantity') }} × {{ data_get($line, 'currency') }} {{ data_get($line, 'price') }}</span></div>
                                    @empty
                                        <p class="text-slate-500">The draft is empty.</p>
                                    @endforelse
                                </div>
                            @elseif (is_array(data_get($toolResult, 'result.results')))
                                <div class="mt-2 grid gap-2">
                                    @foreach (data_get($toolResult, 'result.results', []) as $product)
                                        <div><span class="font-semibold">{{ data_get($product, 'code') }}</span> · {{ data_get($product, 'name') }} <span class="text-slate-500">{{ data_get($product, 'price.currency') }} {{ data_get($product, 'price.amount', 'Price unavailable') }}</span></div>
                                    @endforeach
                                </div>
                            @elseif (data_get($toolResult, 'result.code'))
                                <p class="mt-2 font-semibold">{{ data_get($toolResult, 'result.code') }} · {{ data_get($toolResult, 'result.name') }}</p>
                                <p class="text-slate-500">{{ data_get($toolResult, 'result.price.currency') }} {{ data_get($toolResult, 'result.price.amount', 'Price unavailable') }}</p>
                            @elseif (data_get($toolResult, 'result.status') === 'unknown')
                                <p class="mt-2 text-amber-800">{{ data_get($toolResult, 'result.message') }}</p>
                            @endif
                        </div>
                    @endforeach
                </article>
            @empty
                <div class="grid min-h-96 place-items-center text-center">
                    <div><p class="text-lg font-semibold">How can I help?</p><p class="mt-2 max-w-lg text-sm text-slate-500">Ask me to find account-visible products, check effective prices, inspect orders, or prepare a draft. I cannot submit without your explicit review and confirmation.</p></div>
                </div>
            @endforelse

            @if ($assistantError)
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ $assistantError }}</div>
            @endif
        </div>

        <form wire:submit="send" class="border-t border-slate-200 p-4">
            <div class="flex gap-3">
                <textarea wire:model="message" rows="2" maxlength="{{ config('vendor_assistant.max_message_characters') }}" placeholder="Ask about products, pricing, orders, or your draft…" class="min-w-0 flex-1 resize-none rounded-xl border border-slate-300 px-3 py-2.5"></textarea>
                <button type="submit" wire:loading.attr="disabled" wire:target="send" class="self-end rounded-xl bg-cyan-600 px-5 py-3 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60">Send</button>
            </div>
            @error('message') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            <p wire:loading wire:target="send" class="mt-2 text-xs text-slate-500">The assistant is checking trusted account data…</p>
        </form>
    </section>

    <aside class="space-y-4">
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="flex items-center justify-between gap-3"><h2 class="font-semibold">Order draft</h2><a href="{{ route('portal.orders.create') }}" wire:navigate class="text-xs font-semibold text-cyan-700">Open order form</a></div>
            <div class="mt-4 grid gap-3">
                @forelse ($draftLines as $line)
                    <div class="rounded-xl bg-slate-50 p-3 text-sm"><p class="font-semibold">{{ data_get($line, 'sku') }} · {{ data_get($line, 'name') }}</p><p class="mt-1 text-slate-500">{{ data_get($line, 'quantity') }} × {{ data_get($line, 'currency') }} {{ data_get($line, 'price') }} = {{ data_get($line, 'currency') }} {{ data_get($line, 'total') }}</p></div>
                @empty
                    <p class="text-sm text-slate-500">Your shared order draft is empty.</p>
                @endforelse
            </div>

            @if ($submittedOrder)
                <div class="mt-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-900"><p class="font-semibold">Request {{ $submittedOrder->order_number }} submitted</p><p class="mt-1">Awaiting ERP processing. This is not ERP acceptance and stock is not reserved.</p><a href="{{ route('portal.orders.show', $submittedOrder) }}" wire:navigate class="mt-2 inline-block font-semibold underline">View order</a></div>
            @elseif ($draftLines !== [])
                <button type="button" wire:click="reviewDraft" wire:loading.attr="disabled" class="mt-4 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold">Refresh prices & review</button>
                @if ($reviewedDraftSignature)
                    <label class="mt-4 grid gap-2 text-sm font-medium">Your reference <span class="font-normal text-slate-500">Optional</span><input wire:model="customerReference" type="text" maxlength="255" class="rounded-xl border border-slate-300 px-3 py-2.5"></label>
                    <button type="button" wire:click="confirmDraft" wire:confirm="Submit this order request? Access and prices will be checked again. This does not reserve stock." wire:loading.attr="disabled" class="mt-3 w-full rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Confirm submission</button>
                @endif
            @endif
            @error('draft') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('customerReference') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror
        </section>
        <p class="rounded-xl bg-amber-50 p-4 text-xs leading-5 text-amber-900">Stock availability is currently unknown because no vendor-safe inventory feed is configured. Drafting an order does not reserve stock.</p>
    </aside>
</div>

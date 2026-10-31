<div class="w-full max-w-md rounded-3xl bg-white p-8 shadow-2xl shadow-cyan-950/30 sm:p-10">
    <div class="mb-8"><p class="text-xl font-semibold tracking-tight">Corevia <span class="text-cyan-600">AI</span></p><h1 class="mt-8 text-3xl font-semibold tracking-tight">Vendor portal</h1><p class="mt-2 text-sm text-slate-500">Sign in with your authorised vendor account.</p></div>
    <form wire:submit="authenticate" class="grid gap-5">
        <label class="grid gap-2 text-sm font-medium">Email<input wire:model="email" type="email" autocomplete="username" autofocus class="rounded-xl border border-slate-300 px-4 py-3 outline-none transition focus:border-cyan-600 focus:ring-3 focus:ring-cyan-100">@error('email') <span class="text-sm font-normal text-red-600">{{ $message }}</span> @enderror</label>
        <label class="grid gap-2 text-sm font-medium">Password<input wire:model="password" type="password" autocomplete="current-password" class="rounded-xl border border-slate-300 px-4 py-3 outline-none transition focus:border-cyan-600 focus:ring-3 focus:ring-cyan-100">@error('password') <span class="text-sm font-normal text-red-600">{{ $message }}</span> @enderror</label>
        <label class="flex items-center gap-3 text-sm text-slate-600"><input wire:model="remember" type="checkbox" class="size-4 rounded border-slate-300 text-cyan-600"> Keep me signed in</label>
        <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-slate-950 px-4 py-3 font-semibold text-white transition hover:bg-slate-800 disabled:cursor-wait disabled:opacity-60"><span wire:loading.remove wire:target="authenticate">Sign in</span><span wire:loading wire:target="authenticate">Signing in…</span></button>
    </form>
</div>

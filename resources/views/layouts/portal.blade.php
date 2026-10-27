<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Vendor Portal' }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <div x-data="{ open: false }" class="min-h-screen lg:grid lg:grid-cols-[17rem_minmax(0,1fr)]">
        <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/40 lg:hidden" @click="open = false"></div>
        <aside :class="open ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 flex w-68 flex-col bg-slate-950 text-slate-100 transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
            <div class="flex h-18 items-center justify-between border-b border-white/10 px-6">
                <a href="{{ route('portal.dashboard') }}" wire:navigate class="text-xl font-semibold tracking-tight">Corevia <span class="text-cyan-400">AI</span></a>
                <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-white/10 lg:hidden" @click="open = false" aria-label="Close navigation">✕</button>
            </div>
            <nav class="flex flex-1 flex-col gap-1 p-4" aria-label="Portal navigation">
                @php($links = [['portal.dashboard', 'Dashboard'], ['portal.assistant', 'AI Assistant'], ['portal.products', 'Products & Stock'], ['portal.orders.index', 'Orders'], ['portal.reports', 'Reports'], ['portal.profile', 'Profile']])
                @foreach ($links as [$routeName, $label])
                    <a wire:key="nav-{{ $routeName }}" href="{{ route($routeName) }}" wire:navigate class="rounded-xl px-4 py-3 text-sm font-medium transition {{ request()->routeIs($routeName) || ($routeName === 'portal.orders.index' && request()->routeIs('portal.orders.*')) ? 'bg-cyan-400 text-slate-950' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">{{ $label }}</a>
                @endforeach
                <div class="mt-auto border-t border-white/10 pt-4">
                    <p class="truncate px-4 text-sm font-medium text-white">{{ auth()->user()->name }}</p>
                    <p class="truncate px-4 text-xs text-slate-400">{{ auth()->user()->account?->name }}</p>
                    <form method="POST" action="{{ route('portal.logout') }}" class="mt-3">@csrf<button type="submit" class="w-full rounded-xl px-4 py-3 text-left text-sm font-medium text-slate-300 hover:bg-white/10 hover:text-white">Log out</button></form>
                </div>
            </nav>
        </aside>
        <div class="min-w-0">
            <header class="sticky top-0 z-30 flex h-18 items-center gap-4 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
                <button type="button" class="rounded-lg border border-slate-200 p-2 lg:hidden" @click="open = true" aria-label="Open navigation">☰</button>
                <div class="min-w-0"><p class="truncate text-sm font-semibold">{{ auth()->user()->account?->name }}</p><p class="text-xs text-slate-500">Vendor account {{ auth()->user()->account?->account_number }}</p></div>
            </header>
            <main class="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">{{ $slot }}</main>
        </div>
    </div>
    @livewireScripts
</body>
</html>

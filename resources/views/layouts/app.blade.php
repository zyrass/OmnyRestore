<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'OmnyRestore') }} — @yield('title', 'Espace client')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-[#0D0B08] text-[#F5F0E8]" x-data>

{{-- ========== TOP NAV ========== --}}
<header class="border-b border-[#C9A84C]/10 bg-[#0D0B08]/95 backdrop-blur-md sticky top-0 z-40">
    <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">

        <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-3">
            <div class="w-7 h-7 border border-[#C9A84C] flex items-center justify-center">
                <span class="text-[#C9A84C] text-[9px] font-bold tracking-widest">OR</span>
            </div>
            <span class="font-semibold tracking-[0.15em] text-xs uppercase text-[#F5F0E8]">OmnyRestore</span>
        </a>

        <nav class="hidden md:flex items-center gap-1">
            @if (Auth::user()->role === 'admin')
            {{-- ── Nav Admin ── --}}
            <a href="{{ route('admin.dashboard') }}" wire:navigate
               class="px-4 py-2 text-sm rounded-sm transition-colors {{ request()->routeIs('admin.dashboard') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                Dashboard
            </a>
            <a href="{{ route('admin.orders.index') }}" wire:navigate
               class="px-4 py-2 text-sm rounded-sm transition-colors {{ request()->routeIs('admin.orders.*') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                Commandes
            </a>
            <a href="{{ route('admin.tickets.index') }}" wire:navigate
               class="px-4 py-2 text-sm rounded-sm transition-colors relative {{ request()->routeIs('admin.tickets.*') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                Tickets
                @php $unread = \App\Models\SupportTicket::withCount(['messages as u' => fn($q) => $q->where('is_admin', false)->where('is_read', false)])->having('u', '>', 0)->count(); @endphp
                @if ($unread > 0)
                <span class="absolute -top-1 -right-1 w-4 h-4 text-[9px] bg-[#C9A84C] text-black font-bold rounded-full flex items-center justify-center">
                    {{ $unread > 9 ? '9+' : $unread }}
                </span>
                @endif
            </a>
            @else
            {{-- ── Nav Client ── --}}
            <a href="{{ route('client.orders.index') }}" wire:navigate
               class="px-4 py-2 text-sm rounded-sm transition-colors {{ request()->routeIs('client.orders.index') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                Mes commandes
            </a>
            <a href="{{ route('client.orders.create') }}" wire:navigate
               class="px-4 py-2 text-sm rounded-sm transition-colors {{ request()->routeIs('client.orders.create') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                + Nouvelle commande
            </a>
            <a href="{{ route('client.tickets.index') }}" wire:navigate
               class="px-4 py-2 text-sm rounded-sm transition-colors {{ request()->routeIs('client.tickets.*') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                Support
            </a>
            @endif
        </nav>

        <div class="flex items-center gap-4" x-data="{ open: false }">
            <span class="text-[#7A6E5E] text-sm hidden md:block">{{ Auth::user()->name }}</span>
            <div class="relative">
                <button @click="open = !open"
                        class="w-8 h-8 rounded-full border border-[#C9A84C]/30 bg-[#1A1510] flex items-center justify-center text-[#C9A84C] text-xs font-bold hover:border-[#C9A84C]/60 transition-colors">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </button>
                <div x-show="open" @click.outside="open = false" x-transition
                     class="absolute right-0 top-10 w-52 bg-[#1A1510] border border-[#C9A84C]/15 rounded-sm shadow-xl py-1 z-50">
                    @if (Auth::user()->role === 'admin')
                    <a href="{{ route('admin.dashboard') }}" wire:navigate
                       class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#7A6E5E] hover:text-[#F5F0E8] hover:bg-[#C9A84C]/5 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Dashboard admin
                    </a>
                    @else
                    <a href="{{ route('client.profile') }}" wire:navigate
                       class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#7A6E5E] hover:text-[#F5F0E8] hover:bg-[#C9A84C]/5 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Mon profil
                    </a>
                    @endif
                    <div class="border-t border-[#C9A84C]/10 my-1"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-[#7A6E5E] hover:text-red-400 hover:bg-red-400/5 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Se déconnecter
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

{{-- ========== MAIN ========== --}}
<main class="max-w-6xl mx-auto px-6 py-10">
    @if (session('success'))
    <div class="flex items-center gap-3 bg-emerald-950/50 border border-emerald-500/30 text-emerald-400 rounded-sm px-4 py-3 mb-6 text-sm">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if (session('error'))
    <div class="flex items-center gap-3 bg-red-950/50 border border-red-500/30 text-red-400 rounded-sm px-4 py-3 mb-6 text-sm">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('error') }}
    </div>
    @endif

    {{ $slot }}
</main>

@livewireScripts
</body>
</html>

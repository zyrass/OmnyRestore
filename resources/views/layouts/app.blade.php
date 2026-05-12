<!DOCTYPE html>
{{-- OmnyRestore App Layout --}}
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
    {{-- Chart.js — disponible globalement pour les pages admin --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    @livewireStyles
</head>
<body class="min-h-screen bg-[#0D0B08] text-[#F5F0E8]" x-data x-on:omny:confirm.window="$store.confirmModal.open($event.detail)">

{{-- ========== TOP NAV ========== --}}
<header class="border-b border-[#C9A84C]/10 bg-[#0D0B08]/95 backdrop-blur-md sticky top-0 z-40">
    <div class="max-w-screen-2xl mx-auto px-6 h-16 flex items-center justify-between">

        <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-3">
            <div class="w-7 h-7 border border-[#C9A84C] flex items-center justify-center">
                <span class="text-[#C9A84C] text-[9px] font-bold tracking-widest">OR</span>
            </div>
            <span class="font-semibold tracking-[0.15em] text-xs uppercase text-[#F5F0E8]">OmnyRestore</span>
        </a>

        <nav class="hidden md:flex items-center gap-1">
            @if (Auth::user()->role === 'admin')
            {{-- ── Nav Admin ── --}}
            <a href="{{ route('admin.orders.index') }}" wire:navigate
               class="px-4 py-2 text-sm rounded-sm transition-colors {{ request()->routeIs('admin.orders.*') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                Commandes
            </a>
            <a href="{{ route('admin.tickets.index') }}" wire:navigate
               class="px-4 py-2 text-sm rounded-sm transition-colors relative {{ request()->routeIs('admin.tickets.*') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                Tickets
                @php
                    $unread = \App\Models\SupportTicket::whereHas('messages', fn($q) =>
                        $q->where('is_admin', false)->where('is_read', false)
                    )->count();
                @endphp
                @if ($unread > 0)
                <span class="absolute -top-1 -right-1 w-4 h-4 text-[9px] bg-[#C9A84C] text-black font-bold rounded-full flex items-center justify-center">
                    {{ $unread > 9 ? '9+' : $unread }}
                </span>
                @endif
            </a>
            {{-- ── Réductions ── --}}
            <a href="/admin/coupons" wire:navigate
               class="px-4 py-2 text-sm rounded-sm transition-colors {{ request()->is('admin/coupons*') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                Réductions
            </a>
            {{-- ── Clients ── --}}
            <a href="{{ route('admin.clients') }}" wire:navigate
               class="px-4 py-2 text-sm rounded-sm transition-colors {{ request()->routeIs('admin.clients') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                Clients
            </a>
            {{-- ── CA ── --}}
            <a href="{{ route('admin.revenue') }}" wire:navigate
               class="px-4 py-2 text-sm rounded-sm transition-colors {{ request()->routeIs('admin.revenue') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                CA
            </a>
            {{-- ── Séparateur + Panel Admin (Dashboard) ── --}}
            <div class="w-px h-4 bg-[#C9A84C]/15 mx-1"></div>
            <a href="{{ route('admin.dashboard') }}" wire:navigate
               class="px-3 py-1.5 text-xs font-semibold rounded-sm border transition-all
                      {{ request()->routeIs('admin.dashboard') ? 'border-red-700/60 bg-red-900/20 text-red-400' : 'border-red-800/30 bg-red-900/10 text-red-500 hover:border-red-700/50 hover:text-red-400' }}">
                ⚙ Panel Admin
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
            <div class="hidden md:flex items-center gap-2">
                <span class="text-[#7A6E5E] text-sm">{{ Auth::user()->name }}</span>
                @if (Auth::user()->role === 'admin')
                <span class="text-[9px] font-bold tracking-widest uppercase px-1.5 py-0.5 bg-red-900/30 text-red-400 border border-red-700/40 rounded-full">
                    Admin
                </span>
                @endif
            </div>
            <div class="relative">
                <button @click="open = !open"
                        class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-colors
                               {{ Auth::user()->role === 'admin'
                                  ? 'border-2 border-[#C9A84C] bg-[#C9A84C]/20 text-[#C9A84C] hover:bg-[#C9A84C]/30'
                                  : 'border border-[#C9A84C]/30 bg-[#1A1510] text-[#C9A84C] hover:border-[#C9A84C]/60' }}">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </button>
                <div x-show="open" @click.outside="open = false" x-transition
                     class="absolute right-0 top-10 w-52 bg-[#1A1510] border border-[#C9A84C]/15 rounded-sm shadow-xl py-1 z-50">
                    @if (Auth::user()->role === 'admin')
                    <a href="{{ route('admin.dashboard') }}" wire:navigate
                       class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-500 hover:text-red-400 hover:bg-red-400/5 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        ⚙ Panel Admin
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
<main class="max-w-screen-lg mx-auto px-6 py-10">
    {{-- Flash messages globaux (non affichés sur les pages Livewire qui gèrent leur propre feedback) --}}
    @unless (request()->routeIs('admin.orders.show'))
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
    @endunless

    {!! isset($slot) ? $slot : $__env->yieldContent('content') !!}
</main>

{{-- ═══ Modal de confirmation global ══════════════════════════════════ --}}
<div x-data
     x-show="$store.confirmModal.show"
     x-transition.opacity
     style="display:none;"
     class="fixed inset-0 z-[200] flex items-center justify-center p-4"
     aria-modal="true">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"
         @click="$store.confirmModal.cancel()"></div>

    {{-- Boîte de dialogue --}}
    <div x-show="$store.confirmModal.show"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="relative w-full max-w-md bg-[#141008] border border-[#C9A84C]/20 rounded-sm shadow-2xl overflow-hidden">

        {{-- Bandeau top --}}
        <div class="h-0.5 w-full"
             :style="{ background: $store.confirmModal.danger
                ? 'linear-gradient(to right, #ef4444, #dc2626)'
                : 'linear-gradient(to right, #C9A84C, #E8C97A)' }">
        </div>

        <div class="p-6">
            {{-- Icône --}}
            <div class="flex items-center gap-4 mb-5">
                <div class="shrink-0 w-12 h-12 rounded-full flex items-center justify-center"
                     :style="{ background: $store.confirmModal.danger ? 'rgba(239,68,68,0.12)' : 'rgba(201,168,76,0.12)',
                               border: $store.confirmModal.danger ? '1px solid rgba(239,68,68,0.3)' : '1px solid rgba(201,168,76,0.3)' }">
                    <template x-if="$store.confirmModal.danger">
                        <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </template>
                    <template x-if="!$store.confirmModal.danger">
                        <svg class="w-6 h-6 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </template>
                </div>
                <div>
                    <h3 class="text-[#F5F0E8] font-semibold text-base" x-text="$store.confirmModal.title"></h3>
                    <p class="text-[#7A6E5E] text-sm mt-0.5" x-text="$store.confirmModal.message"></p>
                </div>
            </div>

            {{-- Boutons --}}
            <div class="flex items-center justify-end gap-3">
                <button @click="$store.confirmModal.cancel()"
                        class="px-4 py-2 text-sm text-[#7A6E5E] border border-[#7A6E5E]/25 rounded-sm hover:border-[#7A6E5E]/60 hover:text-[#F5F0E8] transition-all">
                    Annuler
                </button>
                <button @click="$store.confirmModal.confirm()"
                        :style="$store.confirmModal.danger
                            ? 'background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.4);color:#f87171;'
                            : 'background:rgba(201,168,76,0.15);border:1px solid rgba(201,168,76,0.4);color:#C9A84C;'"
                        class="px-5 py-2 text-sm font-medium rounded-sm hover:opacity-80 transition-all"
                        x-text="$store.confirmModal.confirmLabel">
                </button>
            </div>
        </div>
    </div>
</div>

@livewireScripts

<script>
/**
 * Initialise le store Alpine confirmModal.
 * Compatible wire:navigate : appel aussi sur livewire:navigated
 * car alpine:init ne se déclenche qu'une seule fois.
 */
function initConfirmModalStore() {
    if (typeof Alpine === 'undefined') return;
    Alpine.store('confirmModal', {
        show: false,
        title: '',
        message: '',
        confirmLabel: 'Confirmer',
        danger: false,
        _resolve: null,

        open(detail) {
            this.title        = detail.title        || 'Confirmation';
            this.message      = detail.message      || 'Êtes-vous sûr ?';
            this.confirmLabel = detail.confirmLabel || 'Confirmer';
            this.danger       = detail.danger       !== undefined ? detail.danger : true;
            this._resolve     = detail.callback     || null;
            this.show = true;
        },

        confirm() {
            this.show = false;
            if (typeof this._resolve === 'function') this._resolve();
            this._resolve = null;
        },

        cancel() {
            this.show = false;
            this._resolve = null;
        }
    });
}

document.addEventListener('alpine:init', initConfirmModalStore);
// wire:navigate re-exécute les scripts mais pas alpine:init → forcer le store
document.addEventListener('livewire:navigated', initConfirmModalStore);

/**
 * Helper global : omnyConfirm({title, message, confirmLabel, danger}) → Promise<void>
 * Usage dans Blade/Alpine :
 *   @click="omnyConfirm({title:'Clore ?', message:'...', danger:true}).then(() => $wire.closeTicket())"
 */
window.omnyConfirm = function(options) {
    return new Promise((resolve) => {
        window.dispatchEvent(new CustomEvent('omny:confirm', {
            detail: { ...options, callback: resolve }
        }));
    });
};
</script>
@stack('scripts')
</body>
</html>

<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;
use App\Models\SupportTicket;
use App\Models\Testimonial;
use Livewire\Attributes\On;

new class extends Component
{
    public int $unreadTickets = 0;
    public int $pendingAvis = 0;
    public int $unreadClientTickets = 0;

    public function mount(): void
    {
        $this->refreshCounts();
    }

    #[On('testimonial-moderated')]
    #[On('refresh-navbar-counts')]
    #[On('echo:testimonials,TestimonialModerated')]
    public function refreshCounts(): void
    {
        if (auth()->check()) {
            if (auth()->user()->role === 'admin') {
                $this->unreadTickets = SupportTicket::whereHas('messages', fn($q) =>
                    $q->where('is_admin', false)->where('is_read', false)
                )->count();

                $this->pendingAvis = Testimonial::pending()->count();
            } else {
                $this->unreadClientTickets = SupportTicket::where('user_id', auth()->id())
                    ->whereHas('messages', fn($q) =>
                        $q->where('is_admin', true)->where('is_read', false)
                    )->count();
            }
        }
    }

    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

<header class="border-b border-[#C9A84C]/10 bg-[#0D0B08]/95 backdrop-blur-md sticky top-0 z-40">
    <div class="max-w-[1400px] mx-auto app-layout h-16 flex items-center justify-between">

        <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-3">
            <img src="{{ asset('images/logo.png') }}" alt="OmnyRestore" class="w-8 h-8 object-contain">
            <span class="font-semibold tracking-[0.15em] text-xs uppercase text-[#F5F0E8]">OmnyRestore</span>
        </a>

        <nav class="hidden md:flex items-center gap-1">
            @if (Auth::user()->role === 'admin')
            {{-- ── Nav Admin ── --}}
            <a href="{{ route('admin.orders.index') }}" wire:navigate
               class="px-4 py-2 text-sm rounded-sm transition-colors {{ request()->routeIs('admin.orders.*') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                Commandes
            </a>
            <a href="{{ route('admin.clients') }}" wire:navigate
               class="px-4 py-2 text-sm rounded-sm transition-colors {{ request()->routeIs('admin.clients') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                Clients
            </a>
            <a href="{{ route('admin.revenue') }}" wire:navigate
               class="px-4 py-2 text-sm rounded-sm transition-colors {{ request()->routeIs('admin.revenue') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                CA
            </a>
            <a href="{{ route('admin.tickets.index') }}" wire:navigate
               class="px-4 py-2 text-sm rounded-sm transition-colors relative {{ request()->routeIs('admin.tickets.*') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                Tickets
                @if ($unreadTickets > 0)
                <span class="absolute -top-1 -right-1 w-4 h-4 text-[9px] bg-[#C9A84C] text-black font-bold rounded-full flex items-center justify-center">
                    {{ $unreadTickets > 9 ? '9+' : $unreadTickets }}
                </span>
                @endif
            </a>
            <a href="{{ route('admin.testimonials.index') }}" wire:navigate
               class="px-4 py-2 text-sm rounded-sm transition-colors relative {{ request()->routeIs('admin.testimonials.*') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                Avis
                @if ($pendingAvis > 0)
                <span class="absolute -top-1 -right-1 w-4 h-4 text-[9px] bg-[#C9A84C] text-black font-bold rounded-full flex items-center justify-center">
                    {{ $pendingAvis > 9 ? '9+' : $pendingAvis }}
                </span>
                @endif
            </a>
            <a href="/admin/coupons" wire:navigate
               class="px-4 py-2 text-sm rounded-sm transition-colors {{ request()->is('admin/coupons*') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                Réductions
            </a>
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
               class="px-4 py-2 text-sm rounded-sm transition-colors relative {{ request()->routeIs('client.tickets.*') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                Support
                @if ($unreadClientTickets > 0)
                <span class="absolute -top-1 -right-1 w-4 h-4 text-[9px] bg-[#C9A84C] text-black font-bold rounded-full flex items-center justify-center">
                    {{ $unreadClientTickets > 9 ? '9+' : $unreadClientTickets }}
                </span>
                @endif
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
                        class="flex items-center gap-1.5 focus:outline-none group">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-colors
                                {{ Auth::user()->role === 'admin'
                                   ? 'border-2 border-[#C9A84C] bg-[#C9A84C]/20 text-[#C9A84C] group-hover:bg-[#C9A84C]/30'
                                   : 'border border-[#C9A84C]/30 bg-[#1A1510] text-[#C9A84C] group-hover:border-[#C9A84C]/60' }}">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <svg class="w-3.5 h-3.5 text-[#7A6E5E] transition-transform duration-200 group-hover:text-[#C9A84C]" 
                         :class="{ 'rotate-180': open }"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" @click.outside="open = false" x-transition
                     class="absolute right-0 top-10 w-52 bg-[#1A1510] border border-[#C9A84C]/15 rounded-sm shadow-xl py-1 z-50">
                    @if (Auth::user()->role === 'admin')
                    <a href="{{ route('admin.dashboard') }}" wire:navigate
                       class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-500 hover:text-red-400 hover:bg-red-400/5 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924-1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        ⚙ Panel Admin
                    </a>
                    <a href="{{ route('admin.incident.response') }}" wire:navigate
                       class="flex items-center justify-between px-4 py-2.5 text-sm text-[#7A6E5E] hover:text-red-400 hover:bg-red-900/10 transition-colors">
                        <span class="flex items-center gap-3">
                            <span class="w-4 h-4 flex items-center justify-center">🚨</span>
                            Gestion de Crise
                        </span>
                    </a>
                    <a href="{{ route('admin.compliance') }}" wire:navigate
                       class="flex items-center justify-between px-4 py-2.5 text-sm text-[#7A6E5E] hover:text-[#C9A84C] hover:bg-[#C9A84C]/5 transition-colors">
                        <span class="flex items-center gap-3">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.965 11.965 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Conformité (Légal)
                        </span>
                    </a>


                    @else
                    <a href="{{ route('client.profile') }}" wire:navigate
                       class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#7A6E5E] hover:text-[#F5F0E8] hover:bg-[#C9A84C]/5 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Mon profil
                    </a>
                    @endif
                    <div class="border-t border-[#C9A84C]/10 my-1"></div>
                    <button wire:click="logout"
                            class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-[#7A6E5E] hover:text-red-400 hover:bg-red-400/5 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Se déconnecter
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>

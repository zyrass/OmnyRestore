<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public int $unreadTickets = 0;
    public int $unreadClientTickets = 0;
    public int $pendingAvis = 0;

    public function mount()
    {
        $this->refreshCounts();
    }

    public function refreshCounts()
    {
        if (Auth::check()) {
            if (Auth::user()->isStaff()) {
                $this->unreadTickets = \App\Models\SupportTicket::where('status', 'open')->count();
                $this->pendingAvis = \App\Models\Testimonial::pending()->count();
            } else {
                $this->unreadClientTickets = \App\Models\SupportTicket::where('user_id', Auth::id())
                    ->where('status', 'open')
                    ->count();
            }
        }
    }

    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>
 
<header wire:poll.30s="refreshCounts" class="border-b border-[#C9A84C]/10 bg-[#0D0B08]/95 backdrop-blur-md sticky top-0 z-40">
    <style>
        /* Force hide scrollbar for this component specifically */
        .no-scrollbar::-webkit-scrollbar {
            display: none !important;
        }
        .no-scrollbar {
            -ms-overflow-style: none !important;
            scrollbar-width: none !important;
        }
    </style>
    <div class="w-full max-w-[1440px] mx-auto app-layout h-20 flex items-center gap-8 px-6">

        {{-- 1. Logo (Gauche - Largeur fixe) --}}
        <div class="flex-none">
            <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-3 shrink-0">
                <img src="{{ asset('images/logo.png') }}" alt="OmnyRestore" class="w-16 h-16 object-contain">
                @if (! Auth::user()->isStaff())
                <span class="font-semibold tracking-[0.15em] text-xs uppercase text-[#F5F0E8] whitespace-nowrap">OmnyRestore</span>
                @endif
            </a>
        </div>

        {{-- 2. Navigation (Centre - Prend tout l'espace restant) --}}
        <div class="hidden md:flex flex-1 justify-center min-w-0">
            <nav class="flex items-center gap-1 md:overflow-visible overflow-x-auto no-scrollbar py-2">
                @if (Auth::user()->isStaff())
                <a href="{{ route('admin.dashboard') }}" wire:navigate
                   class="px-4 py-2 text-sm rounded-sm transition-all whitespace-nowrap font-medium border border-[#C9A84C]/20 
                          {{ request()->routeIs('admin.dashboard') ? 'text-[#C9A84C] bg-[#C9A84C]/10 border-[#C9A84C]/40' : 'text-[#C9A84C]/80 hover:text-[#C9A84C] hover:bg-[#C9A84C]/5 hover:border-[#C9A84C]/40' }}">
                    Panel Staff
                </a>
                @if(Auth::user()->role !== 'marketing')
                <a href="{{ route('admin.orders.index') }}" wire:navigate
                   class="px-4 py-2 text-sm rounded-sm transition-colors whitespace-nowrap {{ request()->routeIs('admin.orders.*') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                    Commandes
                </a>
                @endif
                <a href="{{ route('admin.clients') }}" wire:navigate
                   class="px-4 py-2 text-sm rounded-sm transition-colors whitespace-nowrap {{ request()->routeIs('admin.clients') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                    Clients
                </a>
                @if(Auth::user()->isAdmin())
                <a href="{{ route('admin.revenue') }}" wire:navigate
                   class="px-4 py-2 text-sm rounded-sm transition-colors whitespace-nowrap {{ request()->routeIs('admin.revenue') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                    CA
                </a>
                @endif
                @if(Auth::user()->role !== 'marketing')
                <a href="{{ route('admin.tickets.index') }}" wire:navigate
                   class="px-4 py-2 text-sm rounded-sm transition-colors whitespace-nowrap relative {{ request()->routeIs('admin.tickets.*') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                    Tickets
                    @if ($unreadTickets > 0)
                    <span class="absolute -top-1 -right-1 w-4 h-4 text-[9px] bg-[#C9A84C] text-black font-bold rounded-full flex items-center justify-center">
                        {{ $unreadTickets > 9 ? '9+' : $unreadTickets }}
                    </span>
                    @endif
                </a>
                @endif
                @if(Auth::user()->role !== 'operator')
                <a href="{{ route('admin.testimonials.index') }}" wire:navigate
                   class="px-4 py-2 text-sm rounded-sm transition-colors whitespace-nowrap relative {{ request()->routeIs('admin.testimonials.*') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                    Avis
                    @if ($pendingAvis > 0)
                    <span class="absolute -top-1 -right-1 w-4 h-4 text-[9px] bg-[#C9A84C] text-black font-bold rounded-full flex items-center justify-center">
                        {{ $pendingAvis > 9 ? '9+' : $pendingAvis }}
                    </span>
                    @endif
                </a>
                @endif
                @if(Auth::user()->role !== 'operator')
                <a href="/admin/coupons" wire:navigate
                   class="px-4 py-2 text-sm rounded-sm transition-colors whitespace-nowrap {{ request()->is('admin/coupons*') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                    Réductions
                </a>
                @endif
                @else
                {{-- ── Nav Client ── --}}
                <a href="{{ route('client.orders.index') }}" wire:navigate
                   class="px-4 py-2 text-sm rounded-sm transition-colors whitespace-nowrap {{ request()->routeIs('client.orders.index') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                    Mes commandes
                </a>
                <a href="{{ route('client.orders.create') }}" wire:navigate
                   class="px-4 py-2 text-sm rounded-sm transition-colors whitespace-nowrap {{ request()->routeIs('client.orders.create') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                    + Nouvelle commande
                </a>
                <a href="{{ route('client.tickets.index') }}" wire:navigate
                   class="px-4 py-2 text-sm rounded-sm transition-colors whitespace-nowrap relative {{ request()->routeIs('client.tickets.*') ? 'text-[#C9A84C] bg-[#C9A84C]/10' : 'text-[#7A6E5E] hover:text-[#F5F0E8]' }}">
                    Support
                    @if ($unreadClientTickets > 0)
                    <span class="absolute -top-1 -right-1 w-4 h-4 text-[9px] bg-[#C9A84C] text-black font-bold rounded-full flex items-center justify-center">
                        {{ $unreadClientTickets > 9 ? '9+' : $unreadClientTickets }}
                    </span>
                    @endif
                </a>
                @endif
            </nav>
        </div>

        {{-- 3. Profil (Droite - Largeur fixe) --}}
        <div class="flex-none flex justify-end">
            <div class="flex items-center gap-4" x-data="{ open: false }">
            <div class="hidden md:flex items-center gap-2">
                <span class="text-[#7A6E5E] text-sm">{{ Auth::user()->name }}</span>
                @if (Auth::user()->isStaff())
                <span class="text-[9px] font-bold tracking-widest uppercase px-1.5 py-0.5 bg-emerald-900/30 text-emerald-400 border border-emerald-700/40 rounded-full">
                    {{ Auth::user()->role === 'super-admin' ? 'Super Admin' : (Auth::user()->role === 'operator' ? 'Opérateur' : 'Marketing') }}
                </span>
                @endif
            </div>
            <div class="relative">
                <button @click="open = !open"
                        class="flex items-center gap-1.5 focus:outline-none group">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-colors
                                {{ Auth::user()->isStaff()
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
                    @if (Auth::user()->isStaff())

                    {{-- Outils communs à tout le staff --}}
                    <a href="{{ route('admin.transparency.index') }}" wire:navigate
                       class="flex items-center px-4 py-2.5 text-sm text-[#7A6E5E] hover:text-[#C9A84C] hover:bg-[#C9A84C]/5 transition-colors border-b border-white/5">
                        Transparence Salariale
                    </a>
                    <a href="{{ route('admin.moderation.lexicon') }}" wire:navigate
                       class="flex items-center px-4 py-2.5 text-sm text-[#7A6E5E] hover:text-red-400 hover:bg-red-900/10 transition-colors border-b border-white/5">
                        Lexique Modération
                    </a>

                    {{-- Outils d'administration (Super-Admin) --}}
                    @if(Auth::user()->isAdmin())
                    <a href="{{ route('admin.team.roles') }}" wire:navigate
                       class="flex items-center px-4 py-2.5 text-sm text-[#7A6E5E] hover:text-[#C9A84C] hover:bg-[#C9A84C]/5 transition-colors border-b border-white/5">
                        Gestion de l'Équipe
                    </a>
                    <a href="{{ route('admin.incident.response') }}" wire:navigate
                       class="flex items-center px-4 py-2.5 text-sm text-[#7A6E5E] hover:text-red-400 hover:bg-red-900/10 transition-colors border-b border-white/5">
                        Gestion de Crise
                    </a>
                    <a href="{{ route('admin.compliance') }}" wire:navigate
                       class="flex items-center px-4 py-2.5 text-sm text-[#7A6E5E] hover:text-[#C9A84C] hover:bg-[#C9A84C]/5 transition-colors">
                        Conformité (Légal)
                    </a>
                    @endif


                    @else
                    <a href="{{ route('client.profile') }}" wire:navigate
                       class="flex items-center px-4 py-2.5 text-sm text-[#7A6E5E] hover:text-[#F5F0E8] hover:bg-[#C9A84C]/5 transition-colors">
                        Mon profil
                    </a>
                    @endif
                    <div class="h-px bg-white/5 my-1 mx-2"></div>
                    <button wire:click="logout"
                            class="w-full flex items-center px-4 py-2.5 text-sm text-red-500/70 hover:text-red-400 hover:bg-red-500/5 transition-all">
                        Se déconnecter
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>

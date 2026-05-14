<?php
/**
 * Admin — Dashboard
 * Route: GET /admin/dashboard
 * Middleware: auth, verified, admin
 *
 * Affiche :
 *   - KPIs clés (commandes en attente, CA du mois, taux de conversion)
 *   - File d'attente des commandes PENDING (à prendre en charge)
 *   - Commandes IN_PROGRESS (en cours de restauration)
 *   - Activité récente (dernières commandes payées)
 */

use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Panel Admin')]
class extends Component
{
    public function with(): array
    {
        $now = now();

        return [
            // ── KPIs ──────────────────────────────────────────────────────
            'kpis' => [
                'pending'     => Order::whereHas('user')->where('status', 'PENDING')->count(),
                'in_progress' => Order::whereHas('user')->where('status', 'IN_PROGRESS')->count(),
                'done'        => Order::whereHas('user')->where('status', 'DONE')->count(),
                'paid_month'  => Order::where('payment_status', 'paid')
                                    ->whereMonth('paid_at', $now->month)
                                    ->whereYear('paid_at', $now->year)
                                    ->count(),
                'revenue_month' => Order::where('payment_status', 'paid')
                                    ->whereMonth('paid_at', $now->month)
                                    ->whereYear('paid_at', $now->year)
                                    ->sum('total_price_cents') * 1.20 / 100,
                'total_orders' => Order::whereHas('user')->count(),
                'tickets_open' => SupportTicket::where('status', 'open')->count(),
                'reviews_pending' => Testimonial::where('is_published', false)->whereNull('rejected_at')->count(),
                'users_active' => User::count(),
                'users_deleted' => User::onlyTrashed()->count(),
            ],

            // ── File d'attente PENDING ────────────────────────────────────
            'pending_orders' => Order::whereHas('user')
                ->where('status', 'PENDING')
                ->with(['user' => fn($u) => $u->withTrashed()])
                ->oldest()  // Plus ancienne en premier (FIFO)
                ->limit(10)
                ->get(),

            // ── En cours IN_PROGRESS ──────────────────────────────────────
            'in_progress_orders' => Order::whereHas('user')
                ->where('status', 'IN_PROGRESS')
                ->with(['user' => fn($u) => $u->withTrashed()])
                ->latest('updated_at')
                ->limit(5)
                ->get(),

            // ── Dernières payées ──────────────────────────────────────────
            'recent_paid' => Order::where('payment_status', 'paid')
                ->with(['user' => fn($u) => $u->withTrashed()])
                ->latest('paid_at')
                ->limit(5)
                ->get(),
        ];
    }
}; ?>

<div wire:poll.5s>
    {{-- En-tête --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-[#F5F0E8]">Panel Admin</h1>
            <p class="text-[#7A6E5E] text-sm mt-1">{{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</p>
        </div>
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.orders.index') }}" wire:navigate class="btn-gold text-sm">
                Consulter toutes les commandes
            </a>
        </div>
    </div>

    {{-- ── KPIs ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        @foreach([
            ['label' => 'En attente',    'value' => $kpis['pending'],       'sub' => 'À traiter',       'color' => 'text-orange-400',  'bg' => 'bg-orange-500/10 border-orange-500/25',  'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'En cours',      'value' => $kpis['in_progress'],   'sub' => 'Restauration',    'color' => 'text-blue-400',    'bg' => 'bg-blue-500/10 border-blue-500/25',      'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'],
            ['label' => 'Aperçus prêts', 'value' => $kpis['done'],          'sub' => 'Attente paiement','color' => 'text-[#C9A84C]',   'bg' => 'bg-[#C9A84C]/10 border-[#C9A84C]/25',   'icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'],
            ['label' => 'CA TTC du mois', 'value' => number_format($kpis['revenue_month'], 2, ',', ' ') . ' €', 'sub' => $kpis['paid_month'] . ' commandes payées', 'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10 border-emerald-500/25', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ] as $kpi)
        <div class="card-glass p-5 border {{ $kpi['bg'] }}">
            <div class="flex items-start justify-between mb-3">
                <p class="text-[#7A6E5E] text-xs tracking-widest uppercase">{{ $kpi['label'] }}</p>
                <svg class="w-4 h-4 {{ $kpi['color'] }} opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kpi['icon'] }}"/></svg>
            </div>
            <p class="text-2xl font-bold {{ $kpi['color'] }}">{{ $kpi['value'] }}</p>
            <p class="text-[#7A6E5E] text-xs mt-1">{{ $kpi['sub'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @foreach([
            ['label' => 'Tickets ouverts',   'value' => $kpis['tickets_open'],    'sub' => 'À prendre en charge', 'color' => 'text-rose-400',    'bg' => 'bg-rose-500/10 border-rose-500/25',      'icon' => 'M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z'],
            ['label' => 'Avis clients',      'value' => $kpis['reviews_pending'], 'sub' => 'En attente de modération', 'color' => 'text-purple-400',  'bg' => 'bg-purple-500/10 border-purple-500/25',  'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z'],
            ['label' => 'Clients actifs',    'value' => $kpis['users_active'],    'sub' => 'Comptes existants',   'color' => 'text-[#C9A84C]',   'bg' => 'bg-[#C9A84C]/10 border-[#C9A84C]/25',   'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['label' => 'Comptes supprimés', 'value' => $kpis['users_deleted'],   'sub' => 'Clients inactifs',    'color' => 'text-[#7A6E5E]',   'bg' => 'bg-[#1A1510] border-[#C9A84C]/10',      'icon' => 'M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6z M21 12h-6'],
        ] as $kpi)
        <div class="card-glass p-5 border {{ $kpi['bg'] }}">
            <div class="flex items-start justify-between mb-3">
                <p class="text-[#7A6E5E] text-xs tracking-widest uppercase">{{ $kpi['label'] }}</p>
                <svg class="w-4 h-4 {{ $kpi['color'] }} opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kpi['icon'] }}"/></svg>
            </div>
            <p class="text-2xl font-bold {{ $kpi['color'] }}">{{ $kpi['value'] }}</p>
            <p class="text-[#7A6E5E] text-xs mt-1">{{ $kpi['sub'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">

        {{-- ── File d'attente PENDING ── --}}
        <div class="card-glass overflow-hidden flex flex-col h-full" style="min-height: 600px;">
            <div class="px-5 py-4 border-b border-[#C9A84C]/10 flex items-center justify-between">
                <h2 class="text-[#F5F0E8] font-semibold text-sm">
                    File d'attente
                    @if ($kpis['pending'] > 0)
                    <span class="ml-2 inline-flex items-center justify-center w-5 h-5 text-xs bg-yellow-500 text-black font-bold rounded-full">{{ $kpis['pending'] }}</span>
                    @endif
                </h2>
                <span class="text-[#7A6E5E] text-xs">Plus ancienne en premier</span>
            </div>

            @if ($pending_orders->isEmpty())
            <div class="px-5 py-10 text-center flex-grow flex flex-col justify-center items-center">
                <p class="text-[#7A6E5E] text-sm">Aucune commande en attente</p>
            </div>
            @else
            <div class="divide-y divide-[#C9A84C]/8">
                @foreach ($pending_orders as $order)
                <div class="px-5 py-3.5 flex items-center justify-between hover:bg-[#C9A84C]/3 transition-colors">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-[#C9A84C] text-xs">{{ $order->reference }}</span>
                            <span class="text-[#7A6E5E] text-xs">·</span>
                            <span class="text-[#7A6E5E] text-xs truncate">{{ $order->user?->name ?? 'Utilisateur supprimé' }}</span>
                        </div>
                        <div class="flex items-center gap-3 mt-0.5">
                            <span class="text-[#F5F0E8] text-xs">{{ $order->photo_count }} photo{{ $order->photo_count > 1 ? 's' : '' }}</span>
                            <span class="text-[#7A6E5E] text-xs">{{ $order->created_at->diffForHumans() }}</span>
                            @if ($order->damage_level === 'heavy')
                            <span class="text-orange-400 text-[10px] border border-orange-500/30 px-1.5 py-0.5 rounded-full">Complète</span>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('admin.orders.show', $order) }}" wire:navigate
                       class="ml-3 px-3 py-1.5 text-xs bg-yellow-500/20 text-yellow-400 border border-yellow-500/30 hover:bg-yellow-500/30 rounded-sm transition-all shrink-0">
                        Prendre en charge →
                    </a>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- ── En cours IN_PROGRESS ── --}}
        <div class="card-glass overflow-hidden flex flex-col h-full" style="min-height: 600px;">
            <div class="px-5 py-4 border-b border-[#C9A84C]/10">
                <h2 class="text-[#F5F0E8] font-semibold text-sm">En cours de restauration</h2>
            </div>
            @if ($in_progress_orders->isEmpty())
            <div class="px-5 py-10 text-center flex-grow flex flex-col justify-center items-center">
                <p class="text-[#7A6E5E] text-sm">Aucune commande en cours</p>
            </div>
            @else
            <div class="divide-y divide-[#C9A84C]/8">
                @foreach ($in_progress_orders as $order)
                <div class="px-5 py-3 flex items-center justify-between hover:bg-[#C9A84C]/3 transition-colors">
                    <div>
                        <span class="font-mono text-[#C9A84C] text-xs">{{ $order->reference }}</span>
                        <span class="text-[#7A6E5E] text-xs ml-2">{{ $order->user?->name ?? 'Utilisateur supprimé' }}</span>
                        <p class="text-[#7A6E5E] text-xs mt-0.5">{{ $order->photo_count }} photo{{ $order->photo_count > 1 ? 's' : '' }} · mis à jour {{ $order->updated_at->diffForHumans() }}</p>
                    </div>
                    <a href="{{ route('admin.orders.show', $order) }}" wire:navigate
                       class="ml-3 px-3 py-1.5 text-xs border border-[#C9A84C]/25 text-[#C9A84C] hover:border-[#C9A84C]/60 rounded-sm transition-all shrink-0">
                        Voir →
                    </a>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- ── Derniers paiements (Pleine largeur) ── --}}
    <div class="card-glass overflow-hidden w-full mb-8">
        <div class="px-5 py-4 border-b border-[#C9A84C]/10">
            <h2 class="text-[#F5F0E8] font-semibold text-sm">Derniers paiements</h2>
        </div>
        @if ($recent_paid->isEmpty())
        <div class="px-5 py-6 text-center text-[#7A6E5E] text-sm">Aucun paiement encore</div>
        @else
        <div class="divide-y divide-[#C9A84C]/8">
            @foreach ($recent_paid as $order)
            <div class="px-5 py-3 flex items-center justify-between">
                <div>
                    <span class="font-mono text-emerald-400 text-xs">{{ $order->reference }}</span>
                    <span class="text-[#7A6E5E] text-xs ml-2">{{ $order->user?->name ?? 'Utilisateur supprimé' }}</span>
                    <p class="text-[#7A6E5E] text-xs mt-0.5">{{ $order->paid_at?->format('d/m/Y H:i') }}</p>
                </div>
                <span class="text-emerald-400 font-semibold text-sm">
                    +{{ number_format($order->getAmountTtcCents() / 100, 2, ',', ' ') }} € TTC
                </span>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- ── Raccourcis ── --}}
    <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="{{ route('admin.clients') }}" wire:navigate
           class="card-glass p-6 flex items-center gap-4 border border-[#C9A84C]/10 hover:border-[#C9A84C]/30 transition-all group">
            <div class="w-10 h-10 rounded-sm bg-[#C9A84C]/10 border border-[#C9A84C]/20 flex items-center justify-center shrink-0 group-hover:bg-[#C9A84C]/20 transition-colors">
                <svg class="w-5 h-5 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-[#F5F0E8] font-semibold text-sm">Voir la liste des clients</p>
                <p class="text-[#7A6E5E] text-xs mt-0.5">Base clients · CA payé · Historique commandes</p>
            </div>
            <svg class="w-4 h-4 text-[#7A6E5E] ml-auto group-hover:text-[#C9A84C] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
        <a href="{{ route('admin.revenue') }}" wire:navigate
           class="card-glass p-6 flex items-center gap-4 border border-emerald-500/10 hover:border-emerald-500/30 transition-all group">
            <div class="w-10 h-10 rounded-sm bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center shrink-0 group-hover:bg-emerald-500/20 transition-colors">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <div>
                <p class="text-[#F5F0E8] font-semibold text-sm">Accéder aux chiffres d'affaires</p>
                <p class="text-[#7A6E5E] text-xs mt-0.5">Graphes mensuels HT / TTC · 12 mois glissants</p>
            </div>
            <svg class="w-4 h-4 text-[#7A6E5E] ml-auto group-hover:text-emerald-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>

</div>


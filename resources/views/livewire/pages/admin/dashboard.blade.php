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
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Dashboard Admin')]
class extends Component
{
    public function with(): array
    {
        $now = now();

        return [
            // ── KPIs ──────────────────────────────────────────────────────
            'kpis' => [
                'pending'     => Order::where('status', 'PENDING')->count(),
                'in_progress' => Order::where('status', 'IN_PROGRESS')->count(),
                'done'        => Order::where('status', 'DONE')->count(),
                'paid_month'  => Order::where('payment_status', 'paid')
                                    ->whereMonth('paid_at', $now->month)
                                    ->whereYear('paid_at', $now->year)
                                    ->count(),
                'revenue_month' => Order::where('payment_status', 'paid')
                                    ->whereMonth('paid_at', $now->month)
                                    ->whereYear('paid_at', $now->year)
                                    ->sum('total_price_cents') / 100,
                'total_orders' => Order::count(),
            ],

            // ── File d'attente PENDING ────────────────────────────────────
            'pending_orders' => Order::where('status', 'PENDING')
                ->with('user')
                ->oldest()  // Plus ancienne en premier (FIFO)
                ->limit(10)
                ->get(),

            // ── En cours IN_PROGRESS ──────────────────────────────────────
            'in_progress_orders' => Order::where('status', 'IN_PROGRESS')
                ->with('user')
                ->latest('updated_at')
                ->limit(5)
                ->get(),

            // ── Dernières payées ──────────────────────────────────────────
            'recent_paid' => Order::where('payment_status', 'paid')
                ->with('user')
                ->latest('paid_at')
                ->limit(5)
                ->get(),
            // ── Tableau clients ───────────────────────────────────────────────────────
            'clients' => \App\Models\User::where('role', 'client')
                ->withCount('orders')
                ->withSum('orders as total_spent_cents', 'total_price_cents')
                ->latest()
                ->limit(20)
                ->get(),
        ];
    }
}; ?>

<div wire:poll.10s>
    {{-- En-tête --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-[#F5F0E8]">Dashboard</h1>
            <p class="text-[#7A6E5E] text-sm mt-1">{{ now()->format('l d F Y') }}</p>
        </div>
        <div class="flex items-center gap-4">
            {{-- Indicateur auto-refresh --}}
            <div class="flex items-center gap-1.5 text-[#7A6E5E] text-xs">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                En direct · {{ now()->format('H:i:s') }}
            </div>
            <a href="{{ route('admin.orders.index') }}" wire:navigate class="btn-gold text-sm">
                Toutes les commandes
            </a>
        </div>
    </div>

    {{-- ── KPIs ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @foreach([
            ['label' => 'En attente',    'value' => $kpis['pending'],       'sub' => 'À traiter',       'color' => 'text-yellow-400',  'bg' => 'bg-yellow-500/10 border-yellow-500/25',  'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'En cours',      'value' => $kpis['in_progress'],   'sub' => 'Restauration',    'color' => 'text-blue-400',    'bg' => 'bg-blue-500/10 border-blue-500/25',      'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'],
            ['label' => 'Aperçus prêts', 'value' => $kpis['done'],          'sub' => 'Attente paiement','color' => 'text-[#C9A84C]',   'bg' => 'bg-[#C9A84C]/10 border-[#C9A84C]/25',   'icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'],
            ['label' => 'CA du mois',    'value' => number_format($kpis['revenue_month'], 2, ',', ' ') . ' €', 'sub' => $kpis['paid_month'] . ' commandes payées', 'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10 border-emerald-500/25', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        {{-- ── File d'attente PENDING ── --}}
        <div class="card-glass overflow-hidden">
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
            <div class="px-5 py-10 text-center">
                <svg class="w-8 h-8 text-emerald-500/30 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-[#7A6E5E] text-sm">Aucune commande en attente 🎉</p>
            </div>
            @else
            <div class="divide-y divide-[#C9A84C]/8">
                @foreach ($pending_orders as $order)
                <div class="px-5 py-3.5 flex items-center justify-between hover:bg-[#C9A84C]/3 transition-colors">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-[#C9A84C] text-xs">{{ $order->reference }}</span>
                            <span class="text-[#7A6E5E] text-xs">·</span>
                            <span class="text-[#7A6E5E] text-xs truncate">{{ $order->user->name }}</span>
                        </div>
                        <div class="flex items-center gap-3 mt-0.5">
                            <span class="text-[#F5F0E8] text-xs">{{ $order->photo_count }} photo{{ $order->photo_count > 1 ? 's' : '' }}</span>
                            <span class="text-[#7A6E5E] text-xs">{{ $order->created_at->diffForHumans() }}</span>
                            @if ($order->damage_level === 'heavy')
                            <span class="text-orange-400 text-[10px] border border-orange-500/30 px-1.5 py-0.5 rounded-full">Avancée</span>
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

        {{-- ── Droite : EN COURS + PAYÉES RÉCENTES ── --}}
        <div class="space-y-6">

            {{-- En cours --}}
            <div class="card-glass overflow-hidden">
                <div class="px-5 py-4 border-b border-[#C9A84C]/10">
                    <h2 class="text-[#F5F0E8] font-semibold text-sm">En cours de restauration</h2>
                </div>
                @if ($in_progress_orders->isEmpty())
                <div class="px-5 py-6 text-center text-[#7A6E5E] text-sm">Aucune commande en cours</div>
                @else
                <div class="divide-y divide-[#C9A84C]/8">
                    @foreach ($in_progress_orders as $order)
                    <div class="px-5 py-3 flex items-center justify-between hover:bg-[#C9A84C]/3 transition-colors">
                        <div>
                            <span class="font-mono text-[#C9A84C] text-xs">{{ $order->reference }}</span>
                            <span class="text-[#7A6E5E] text-xs ml-2">{{ $order->user->name }}</span>
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

            {{-- Payées récentes --}}
            <div class="card-glass overflow-hidden">
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
                            <span class="text-[#7A6E5E] text-xs ml-2">{{ $order->user->name }}</span>
                            <p class="text-[#7A6E5E] text-xs mt-0.5">{{ $order->paid_at?->format('d/m/Y H:i') }}</p>
                        </div>
                        <span class="text-emerald-400 font-semibold text-sm">
                            +{{ number_format(($order->total_price_cents ?? $order->base_price_cents ?? 0) / 100, 2, ',', ' ') }} €
                        </span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Tableau Clients ── --}}
    <div class="mt-8">
        <div class="card-glass overflow-hidden">
            <div class="px-5 py-4 border-b border-[#C9A84C]/10 flex items-center justify-between">
                <h2 class="text-[#F5F0E8] font-semibold text-sm">Clients</h2>
                <span class="text-[#7A6E5E] text-xs">{{ $clients->count() }} clients enregistrés</span>
            </div>
            @if ($clients->isEmpty())
            <div class="px-5 py-10 text-center text-[#7A6E5E] text-sm">Aucun client pour le moment.</div>
            @else
            <table class="w-full">
                <thead>
                    <tr class="border-b border-[#C9A84C]/10">
                        <th class="px-5 py-3 text-left text-xs text-[#7A6E5E] uppercase tracking-widest">Client</th>
                        <th class="px-4 py-3 text-center text-xs text-[#7A6E5E] uppercase tracking-widest">Commandes</th>
                        <th class="px-4 py-3 text-right text-xs text-[#7A6E5E] uppercase tracking-widest">Dépensé HT</th>
                        <th class="px-4 py-3 text-right text-xs text-[#7A6E5E] uppercase tracking-widest">Inscription</th>
                        <th class="px-4 py-3 text-right text-xs text-[#7A6E5E] uppercase tracking-widest">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#C9A84C]/5">
                    @foreach ($clients as $client)
                    <tr class="hover:bg-[#C9A84C]/3 transition-colors">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-7 h-7 rounded-full border border-[#C9A84C]/30 bg-[#1A1510] text-[#C9A84C] text-xs font-bold flex items-center justify-center">
                                    {{ strtoupper(substr($client->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="text-[#F5F0E8] text-sm font-medium">{{ $client->name }}</div>
                                    <div class="text-[#7A6E5E] text-xs">{{ $client->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-[#F5F0E8] text-sm font-semibold">{{ $client->orders_count }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span class="text-[#C9A84C] text-sm font-semibold">
                                {{ number_format(($client->total_spent_cents ?? 0) / 100, 2, ',', ' ') }} €
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-[#7A6E5E] text-xs">
                            {{ $client->created_at->format('d/m/Y') }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.orders.index') }}?search={{ urlencode($client->email) }}"
                               wire:navigate class="text-xs text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
                                Voir commandes →
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

</div>

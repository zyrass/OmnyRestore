<?php
/**
 * Admin — Chiffre d'Affaire (CA)
 * Route: GET /admin/revenue
 * Middleware: auth, verified, admin
 *
 * Affiche les revenus mensuels (12 derniers mois) avec :
 *  - KPIs globaux : CA total HT, CA total TTC, nb commandes payées
 *  - Graphe Chart.js : CA HT mois par mois (barres) + CA TTC (ligne)
 *  - Tableau détaillé mois par mois
 */

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;

new
#[Layout('layouts.app')]
#[Title('Chiffre d\'Affaire — Admin')]
class extends Component
{
    public function with(): array
    {
        // ── 12 mois glissants ─────────────────────────────────────────────
        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $months->push(now()->subMonths($i)->startOfMonth());
        }

        // PostgreSQL : TO_CHAR() — MySQL utilisait DATE_FORMAT() qui n'existe pas en PG
        // GROUP BY doit répéter l'expression (PG ne supporte pas les alias dans GROUP BY)
        $pgExpr = "TO_CHAR(paid_at, 'YYYY-MM')";

        $rawData = Order::where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->subMonths(11)->startOfMonth())
            ->select(
                DB::raw("{$pgExpr} as month_key"),
                DB::raw('SUM(total_price_cents) as total_ht_cents'),
                DB::raw('COUNT(*) as order_count')
            )
            ->groupBy(DB::raw($pgExpr))
            ->orderBy(DB::raw($pgExpr))
            ->get()
            ->keyBy('month_key');

        // Mois en français (sans dépendance à Carbon locale)
        $moisFr = [
            1 => 'Janvier', 2 => 'Février',  3 => 'Mars',     4 => 'Avril',
            5 => 'Mai',     6 => 'Juin',      7 => 'Juillet',  8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];

        $chartLabels  = [];
        $chartHt      = [];
        $chartTtc     = [];
        $tableRows    = [];

        foreach ($months as $m) {
            $key      = $m->format('Y-m');
            $label    = $moisFr[(int) $m->format('n')] . ' ' . $m->format('Y');
            $htCents  = (int) ($rawData[$key]->total_ht_cents ?? 0);
            $ttcCents = $htCents + (int) round($htCents * 0.2);
            $orders   = (int) ($rawData[$key]->order_count ?? 0);

            $chartLabels[] = $label;
            $chartHt[]     = round($htCents / 100, 2);
            $chartTtc[]    = round($ttcCents / 100, 2);
            $tableRows[]   = [
                'label'      => $label,
                'ht_cents'   => $htCents,
                'ttc_cents'  => $ttcCents,
                'orders'     => $orders,
            ];
        }

        // ── KPIs globaux (all time) ───────────────────────────────────────
        $allTimePaid = Order::where('payment_status', 'paid');
        $totalHt     = (int) (clone $allTimePaid)->sum('total_price_cents');
        $totalTtc    = $totalHt + (int) round($totalHt * 0.2);
        $totalOrders = (clone $allTimePaid)->count();

        return compact('chartLabels', 'chartHt', 'chartTtc', 'tableRows', 'totalHt', 'totalTtc', 'totalOrders');
    }
}; ?>

{{-- Chart.js CDN --}}
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
@endpush

<div>
    {{-- En-tête --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-[#F5F0E8]">Chiffre d'Affaire</h1>
            <p class="text-[#7A6E5E] text-sm mt-1">12 derniers mois · Commandes payées uniquement</p>
        </div>
        <a href="{{ route('admin.clients') }}" wire:navigate
           class="inline-flex items-center gap-2 px-4 py-2 text-sm border border-[#C9A84C]/30 text-[#C9A84C] hover:bg-[#C9A84C]/10 rounded-sm transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Voir les clients
        </a>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="card-glass p-5 border border-[#C9A84C]/20">
            <p class="text-[#7A6E5E] text-xs uppercase tracking-widest mb-1">CA total HT</p>
            <p class="text-[#C9A84C] text-2xl font-bold">{{ number_format($totalHt / 100, 2, ',', ' ') }} €</p>
            <p class="text-[#7A6E5E]/60 text-xs mt-1">Hors taxes · all time</p>
        </div>
        <div class="card-glass p-5 border border-emerald-500/20">
            <p class="text-[#7A6E5E] text-xs uppercase tracking-widest mb-1">CA total TTC</p>
            <p class="text-emerald-400 text-2xl font-bold">{{ number_format($totalTtc / 100, 2, ',', ' ') }} €</p>
            <p class="text-[#7A6E5E]/60 text-xs mt-1">TVA 20% incluse · all time</p>
        </div>
        <div class="card-glass p-5">
            <p class="text-[#7A6E5E] text-xs uppercase tracking-widest mb-1">Commandes payées</p>
            <p class="text-[#F5F0E8] text-2xl font-bold">{{ $totalOrders }}</p>
            <p class="text-[#7A6E5E]/60 text-xs mt-1">Total historique</p>
        </div>
    </div>

    {{-- Graphe --}}
    <div class="card-glass p-6 mb-8">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[#F5F0E8] font-semibold text-sm">Évolution mensuelle</h2>
            <div class="flex items-center gap-4 text-xs text-[#7A6E5E]">
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-[#C9A84C] inline-block"></span> CA HT
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-emerald-400 inline-block"></span> CA TTC
                </span>
            </div>
        </div>
        <div class="relative h-72">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    {{-- Tableau mois par mois --}}
    <div class="card-glass overflow-hidden">
        <div class="px-5 py-4 border-b border-[#C9A84C]/10">
            <h2 class="text-[#F5F0E8] font-semibold text-sm">Détail mensuel</h2>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[#C9A84C]/10">
                    <th class="px-5 py-3 text-left text-xs text-[#7A6E5E] uppercase tracking-widest">Mois</th>
                    <th class="px-4 py-3 text-center text-xs text-[#7A6E5E] uppercase tracking-widest">Commandes</th>
                    <th class="px-4 py-3 text-right text-xs text-[#7A6E5E] uppercase tracking-widest">CA HT</th>
                    <th class="px-4 py-3 text-right text-xs text-[#7A6E5E] uppercase tracking-widest">TVA 20%</th>
                    <th class="px-4 py-3 text-right text-xs text-[#7A6E5E] uppercase tracking-widest">CA TTC</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#C9A84C]/5">
                @foreach (array_reverse($tableRows) as $row)
                <tr class="hover:bg-[#C9A84C]/3 transition-colors {{ $row['ht_cents'] === 0 ? 'opacity-40' : '' }}">
                    <td class="px-5 py-3 text-[#F5F0E8] font-medium">{{ $row['label'] }}</td>
                    <td class="px-4 py-3 text-center text-[#7A6E5E]">{{ $row['orders'] }}</td>
                    <td class="px-4 py-3 text-right text-[#C9A84C] font-semibold">
                        {{ number_format($row['ht_cents'] / 100, 2, ',', ' ') }} €
                    </td>
                    <td class="px-4 py-3 text-right text-[#7A6E5E]">
                        {{ number_format(($row['ttc_cents'] - $row['ht_cents']) / 100, 2, ',', ' ') }} €
                    </td>
                    <td class="px-4 py-3 text-right text-emerald-400 font-semibold">
                        {{ number_format($row['ttc_cents'] / 100, 2, ',', ' ') }} €
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Chart.js init --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('revenueChart');
    if (!ctx) return;

    const labels  = @json($chartLabels);
    const htData  = @json($chartHt);
    const ttcData = @json($chartTtc);

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'CA HT (€)',
                    data: htData,
                    backgroundColor: 'rgba(201, 168, 76, 0.25)',
                    borderColor: '#C9A84C',
                    borderWidth: 2,
                    borderRadius: 4,
                    order: 2,
                },
                {
                    label: 'CA TTC (€)',
                    data: ttcData,
                    type: 'line',
                    borderColor: '#34d399',
                    backgroundColor: 'rgba(52, 211, 153, 0.08)',
                    borderWidth: 2,
                    pointBackgroundColor: '#34d399',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.4,
                    fill: true,
                    order: 1,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1C1812',
                    borderColor: 'rgba(201,168,76,0.2)',
                    borderWidth: 1,
                    titleColor: '#F5F0E8',
                    bodyColor: '#7A6E5E',
                    padding: 12,
                    callbacks: {
                        label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y.toFixed(2)} €`
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(201,168,76,0.05)' },
                    ticks: { color: '#7A6E5E', font: { size: 11 } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(201,168,76,0.07)' },
                    ticks: {
                        color: '#7A6E5E',
                        font: { size: 11 },
                        callback: (v) => v.toFixed(2) + ' €'
                    }
                }
            }
        }
    });
});
</script>

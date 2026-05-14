<?php
/**
 * Admin — Chiffre d'Affaire (CA) 2.0
 * Route: GET /admin/revenue
 *
 * Évolutions :
 * - Navigation par Année / Mois (tabs)
 * - Indicateur de croissance (MoM)
 * - Calcul Coût IA (0.15€/photo)
 * - Calcul URSSAF (21.2% du TTC encaissé)
 * - Graphiques journaliers et hebdomadaires
 */

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new
#[Layout('layouts.app')]
#[Title('Chiffre d\'Affaire — Admin')]
class extends Component
{
    #[Url]
    public $year;

    #[Url]
    public $month;

    #[Url]
    public $view = 'month'; // 'month' or 'year'

    public function mount()
    {
        $this->year = $this->year ?? (int) now()->format('Y');
        $this->month = $this->month ?? (int) now()->format('n');
        $this->view = $this->view ?? 'month';
    }

    public function setYear($y) 
    { 
        $this->year = $y; 
        $this->refreshData();
    }

    public function setMonth($m) 
    { 
        $this->month = $m; 
        $this->view = 'month';
        $this->refreshData();
    }

    public function setYearlyView()
    {
        $this->view = 'year';
        $this->refreshData();
    }

    private function refreshData()
    {
        $data = $this->with();
        $this->dispatch('revenue-updated', 
            daily: $data['dailyData'], 
            weekly: $data['weeklyData'],
            labels: $data['dailyLabels'],
            view: $this->view
        );
    }

    public function with(): array
    {
        if ($this->view === 'year') {
            $startRange = Carbon::createFromDate($this->year, 1, 1)->startOfYear();
            $endRange   = $startRange->copy()->endOfYear();
            $prevStart  = $startRange->copy()->subYear();
            $prevEnd    = $prevStart->copy()->endOfYear();
        } else {
            $selectedDate = Carbon::createFromDate($this->year, $this->month, 1);
            $startRange = $selectedDate->copy()->startOfMonth();
            $endRange   = $selectedDate->copy()->endOfMonth();
            $prevStart  = $startRange->copy()->subMonth();
            $prevEnd    = $prevStart->copy()->endOfMonth();
        }

        // ── 1. Données de la période ──────────────────────────────────────
        $orders = Order::where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startRange, $endRange])
            ->get();

        $stats = [
            'ht_cents'    => $orders->sum('total_price_cents'),
            'ttc_cents'   => $orders->sum(fn($o) => $o->total_price_cents + round($o->total_price_cents * 0.2)),
            'count'       => $orders->count(),
            'photos'      => $orders->sum('photo_count'),
            'ai_cost'     => $orders->sum('photo_count') * 15,
            'urssaf'      => 0,
        ];
        $stats['urssaf'] = (int) round($stats['ttc_cents'] * 0.212);
        $stats['net']    = $stats['ht_cents'] - $stats['ai_cost'] - $stats['urssaf'];

        // ── 2. Comparaison période précédente ─────────────────────────────
        $prevHtCents = Order::where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$prevStart, $prevEnd])
            ->sum('total_price_cents');

        $growth = 0;
        if ($prevHtCents > 0) {
            $growth = (($stats['ht_cents'] - $prevHtCents) / $prevHtCents) * 100;
        }

        // ── 3. Graphiques ─────────────────────────────────────────────────
        $dailyData = [];
        $dailyLabels = [];
        $weeklyData = []; // Servira pour CA ou Commandes en vue annuelle

        if ($this->view === 'month') {
            // Vue Mensuelle : Jours et Semaines
            $daysInMonth = $startRange->daysInMonth;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateStr = $startRange->copy()->day($d)->format('Y-m-d');
                $dailyData[$dateStr] = 0;
            }
            foreach ($orders as $order) {
                $date = $order->paid_at->format('Y-m-d');
                if (isset($dailyData[$date])) {
                    $dailyData[$date] += round($order->total_price_cents / 100, 2);
                }
            }
            $dailyLabels = array_keys($dailyData);
            $dailyData = array_values($dailyData);

            $weeklyData = [0, 0, 0, 0, 0];
            foreach ($orders as $order) {
                $weekNum = (int) ceil($order->paid_at->day / 7) - 1;
                if ($weekNum > 4) $weekNum = 4;
                $weeklyData[$weekNum] += round($order->total_price_cents / 100, 2);
            }
        } else {
            // Vue Annuelle : Mois par Mois
            $moisLabels = ['Janv', 'Févr', 'Mars', 'Avril', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];
            $dailyLabels = $moisLabels;
            $dailyData = array_fill(0, 12, 0); // CA par mois
            $weeklyData = array_fill(0, 12, 0); // Commandes par mois

            foreach ($orders as $order) {
                $m = (int)$order->paid_at->format('n') - 1;
                $dailyData[$m] += round($order->total_price_cents / 100, 2);
                $weeklyData[$m] += 1; // Count orders
            }
        }

        // ── 4. Configuration UI ───────────────────────────────────────────
        $years = range(2026, max(2026, (int) now()->year + 1));
        $moisFr = [
            1 => 'Janv', 2 => 'Févr', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai',  6 => 'Juin', 7 => 'Juil', 8 => 'Août',
            9 => 'Sept', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc',
        ];

        // ── 5. Calcul de l'objectif (Simulateur) ─────────────────────────
        $simulatorSettings = \Illuminate\Support\Facades\Cache::get('admin_simulator_settings', []);
        
        $targetNetDirigeant = $simulatorSettings['dirigeant'] ?? 2500;
        $targetNetCollab = $simulatorSettings['collab'] ?? 1800;
        $fixedCosts = $simulatorSettings['fixed'] ?? 150;
        
        $collabInvoice = $targetNetCollab / (1 - 0.212); 
        $averageOrderPrice = $simulatorSettings['averageOrderPrice'] ?? ($stats['count'] > 0 ? ($stats['ttc_cents'] / 100 / $stats['count']) : 19);
        $iaRatio = $simulatorSettings['iaRatio'] ?? ($stats['ttc_cents'] > 0 ? ($stats['ai_cost'] / $stats['ttc_cents'] * 100) : 8.0);
        
        $stripePct = 0.015;
        $stripeFixedRatio = $averageOrderPrice > 0 ? (0.25 / $averageOrderPrice) : 0;
        
        $effectiveMarginRate = 1 - 0.212 - ($iaRatio / 100) - $stripePct - $stripeFixedRatio;
        
        $targetCaTtc = 0;
        if ($effectiveMarginRate > 0) {
            $targetCaTtc = ($targetNetDirigeant + $collabInvoice + $fixedCosts) / $effectiveMarginRate;
        }

        $progressPercentage = $targetCaTtc > 0 ? min(100, round((($stats['ttc_cents'] / 100) / $targetCaTtc) * 100, 1)) : 0;

        return [
            'stats'       => $stats,
            'growth'      => $growth,
            'dailyData'   => $dailyData,
            'dailyLabels' => $dailyLabels,
            'weeklyData'  => $weeklyData,
            'years'       => $years,
            'moisFr'      => $moisFr,
            'targetCaTtc' => $targetCaTtc,
            'progressPercentage' => $progressPercentage,
            'currentLabel'=> $this->view === 'year' ? "Année {$this->year}" : ($moisFr[(int)$this->month] . ' ' . $this->year)
        ];
    }
}; ?>

<div x-data="revenueDashboard()" x-init="init()" class="pb-12">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
        <div>
            <div class="flex items-center gap-3 mb-2">
                <h1 class="text-3xl font-bold text-[#F5F0E8]">Pilotage Financier</h1>
                <span class="px-2.5 py-0.5 rounded-full bg-[#C9A84C]/10 border border-[#C9A84C]/20 text-[#C9A84C] text-[10px] font-bold uppercase tracking-wider">Admin</span>
            </div>
            <p class="text-[#7A6E5E] text-sm max-w-md">Analyse détaillée du chiffre d'affaires, des coûts IA et des obligations URSSAF.</p>
        </div>
        
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2 p-1 bg-[#120F0A] border border-[#C9A84C]/20 rounded-sm">
                @foreach ($years as $y)
                    <button wire:click="setYear({{ $y }})"
                            class="px-4 py-1.5 text-xs font-bold transition-all rounded-sm
                            {{ (int)$year === $y 
                               ? 'bg-[#C9A84C] text-[#120F0A]' 
                               : 'text-[#7A6E5E] hover:text-[#F5F0E8] hover:bg-[#C9A84C]/5' }}">
                        {{ $y }}
                    </button>
                @endforeach
            </div>
            
            <a href="{{ route('admin.revenue.simulation') }}" wire:navigate
               class="px-5 py-2 text-sm font-medium rounded-sm border border-[#C9A84C]/30 text-[#C9A84C] hover:bg-[#C9A84C]/10 transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Simulateur
            </a>
            
            <a href="{{ route('admin.revenue.export', ['year' => $year, 'month' => $month]) }}" 
               class="btn-gold !py-2 px-6 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Export PDF
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[120px_1fr] gap-8">
        {{-- Navigation Mois (Tabs Verticaux) --}}
        <div class="flex flex-row lg:flex-col overflow-x-auto lg:overflow-x-visible gap-1 pb-4 lg:pb-0 scrollbar-hide">
            <button wire:click="setYearlyView"
                    class="flex-shrink-0 px-4 py-3 rounded-sm text-sm font-bold transition-all text-left mb-2
                    {{ $view === 'year' 
                       ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-900/20' 
                       : 'text-emerald-500/70 hover:text-emerald-400 hover:bg-emerald-500/5 border border-emerald-500/10' }}">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Bilan Annuel
                </div>
            </button>

            <div class="h-px bg-[#C9A84C]/10 my-2 hidden lg:block"></div>

            @foreach ($moisFr as $mNum => $mLabel)
                <button wire:click="setMonth({{ $mNum }})"
                        class="flex-shrink-0 px-4 py-3 rounded-sm text-sm font-medium transition-all text-left
                        {{ $view === 'month' && (int)$month === $mNum 
                           ? 'bg-[#C9A84C] text-[#120F0A] shadow-lg shadow-[#C9A84C]/10' 
                           : 'text-[#7A6E5E] hover:text-[#F5F0E8] hover:bg-[#C9A84C]/5' }}">
                    {{ $mLabel }}
                </button>
            @endforeach
        </div>

        <div class="space-y-8">
            {{-- KPIs Top Row --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                {{-- CA HT Mensuel --}}
                <div class="card-glass p-5 border-l-4 border-l-[#C9A84C]">
                    <p class="text-[#7A6E5E] text-[10px] uppercase tracking-widest mb-1">CA HT ({{ $view === 'year' ? 'Année' : 'Mois' }})</p>
                    <div class="flex items-end justify-between">
                        <p class="text-[#F5F0E8] text-2xl font-bold">{{ number_format($stats['ht_cents'] / 100, 2, ',', ' ') }} €</p>
                        @if ($growth !== 0)
                        <span class="text-xs font-bold {{ $growth > 0 ? 'text-emerald-400' : 'text-red-400' }}">
                            {{ $growth > 0 ? '↑' : '↓' }} {{ abs(round($growth, 1)) }}%
                        </span>
                        @endif
                    </div>
                    <p class="text-[#7A6E5E]/60 text-[10px] mt-2">vs {{ $view === 'year' ? 'année' : 'mois' }} précédent</p>
                </div>

                {{-- CA TTC --}}
                <div class="card-glass p-5 border-l-4 border-l-emerald-900/50">
                    <p class="text-[#7A6E5E] text-[10px] uppercase tracking-widest mb-1">CA TTC ({{ $view === 'year' ? 'Année' : 'Mois' }})</p>
                    <p class="text-emerald-400 text-2xl font-bold">{{ number_format($stats['ttc_cents'] / 100, 2, ',', ' ') }} €</p>
                    <p class="text-[#7A6E5E]/60 text-[10px] mt-2">Montant total facturé</p>
                </div>

                {{-- Commandes --}}
                <div class="card-glass p-5">
                    <p class="text-[#7A6E5E] text-[10px] uppercase tracking-widest mb-1">Activité</p>
                    <p class="text-[#F5F0E8] text-2xl font-bold">{{ $stats['count'] }} <span class="text-sm font-normal text-[#7A6E5E]">cmd</span></p>
                    <p class="text-[#7A6E5E]/60 text-[10px] mt-2">{{ $stats['photos'] }} photos traitées</p>
                </div>

                {{-- Coûts IA --}}
                <div class="card-glass p-5 border-l-4 border-l-red-900/50">
                    <p class="text-[#7A6E5E] text-[10px] uppercase tracking-widest mb-1">Coûts IA (0.15€/u)</p>
                    <p class="text-red-400 text-2xl font-bold">{{ number_format($stats['ai_cost'] / 100, 2, ',', ' ') }} €</p>
                    <p class="text-[#7A6E5E]/60 text-[10px] mt-2">Estimation globale</p>
                </div>

                {{-- URSSAF --}}
                <div class="card-glass p-5 border-l-4 border-l-blue-900/50">
                    <p class="text-[#7A6E5E] text-[10px] uppercase tracking-widest mb-1">Cotisations URSSAF</p>
                    <p class="text-blue-400 text-2xl font-bold">{{ number_format($stats['urssaf'] / 100, 2, ',', ' ') }} €</p>
                    <p class="text-[#7A6E5E]/60 text-[10px] mt-2">Taux 21.2% (BNC)</p>
                </div>
            </div>

            {{-- Graphiques --}}
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                {{-- Graphe 1 --}}
                <div class="card-glass p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-[#F5F0E8] font-semibold text-sm">{{ $view === 'year' ? 'Évolution Mensuelle (HT)' : 'Évolution Journalière (HT)' }}</h3>
                        <span class="text-[10px] text-[#7A6E5E] font-mono">{{ $currentLabel }}</span>
                    </div>
                    <div class="h-64" wire:ignore>
                        <canvas id="dailyChart"></canvas>
                    </div>
                </div>

                {{-- Graphe 2 --}}
                <div class="card-glass p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-[#F5F0E8] font-semibold text-sm">{{ $view === 'year' ? 'Volume de Commandes / Mois' : 'Performance par Semaine' }}</h3>
                        <span class="text-[10px] text-[#7A6E5E]">{{ $view === 'year' ? 'Total Année' : 'Mois complet' }}</span>
                    </div>
                    <div class="h-64" wire:ignore>
                        <canvas id="weeklyChart"></canvas>
                    </div>
                </div>
            </div>

            {{-- Résultat Net --}}
            <div class="card-glass p-8 bg-emerald-500/5 border border-emerald-500/20 flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="text-center md:text-left">
                    <h3 class="text-emerald-400 font-bold text-lg mb-1">Résultat Net Estimé</h3>
                    <p class="text-[#7A6E5E] text-sm">Ce qu'il reste après déduction de l'IA et de l'URSSAF.</p>
                </div>
                <div class="text-center md:text-right">
                    <p class="text-emerald-400 text-4xl font-black">{{ number_format($stats['net'] / 100, 2, ',', ' ') }} €</p>
                    <p class="text-[#7A6E5E] text-[10px] mt-1 uppercase tracking-widest">Disponible après taxes</p>
                </div>
            </div>

            {{-- Progression de l'objectif (Simulateur par défaut) --}}
            <div class="card-glass p-8 border-l-4 border-l-[#C9A84C] bg-[#C9A84C]/5">
                <div class="flex flex-col md:flex-row md:items-end justify-between mb-6 gap-4">
                    <div>
                        <h3 class="text-[#F5F0E8] font-bold text-lg">Progression vers l'objectif</h3>
                        <p class="text-[#7A6E5E] text-sm mt-1">Vos objectifs de la simulation : Dirigeant, Collab, et Frais incompressibles</p>
                    </div>
                    <div class="text-left md:text-right">
                        <p class="text-[#C9A84C] text-[10px] uppercase tracking-widest mb-1">Cible TTC à atteindre</p>
                        <p class="text-[#F5F0E8] text-4xl font-black mb-1">{{ number_format($targetCaTtc, 2, ',', ' ') }} €</p>
                        <p class="text-[#7A6E5E] text-sm">
                            Fait : <strong class="text-white">{{ number_format($stats['ttc_cents'] / 100, 2, ',', ' ') }} €</strong>
                        </p>
                    </div>
                </div>
                
                <div class="flex items-center gap-4">
                    <div class="flex-grow bg-[#120F0A] rounded-full h-4 border border-[#C9A84C]/20 overflow-hidden relative">
                        <div class="bg-gradient-to-r from-[#C9A84C]/50 to-[#C9A84C] h-4 rounded-full transition-all duration-1000 ease-out" style="width: {{ $progressPercentage }}%"></div>
                    </div>
                    <span class="text-[#C9A84C] font-black text-xl w-16 text-right">{{ $progressPercentage }}%</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function revenueDashboard() {
    return {
        dailyData: @entangle('dailyData'),
        weeklyData: @entangle('weeklyData'),
        dailyLabels: @json($dailyLabels),
        view: @entangle('view'),
        
        init() {
            // Chargement immédiat avec les données injectées par PHP au rendu
            this.initCharts(@json($dailyData), @json($weeklyData), @json($dailyLabels), @js($view));
            
            window.addEventListener('revenue-updated', (event) => {
                // Mise à jour avec les données reçues par l'événement lors du clic
                this.initCharts(event.detail.daily, event.detail.weekly, event.detail.labels, event.detail.view);
            });
        },
        
        initCharts(daily, weekly, labels, currentView) {
            const dailyCtx = document.getElementById('dailyChart');
            const weeklyCtx = document.getElementById('weeklyChart');

            if (window.dailyChartInstance) window.dailyChartInstance.destroy();
            if (window.weeklyChartInstance) window.weeklyChartInstance.destroy();

            if (dailyCtx) {
                window.dailyChartInstance = new Chart(dailyCtx, {
                    type: 'line',
                    data: {
                        labels: currentView === 'year' ? labels : labels.map(d => d.split('-')[2]),
                        datasets: [{
                            label: 'CA HT (€)',
                            data: daily,
                            borderColor: '#C9A84C',
                            backgroundColor: 'rgba(201, 168, 76, 0.05)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.3,
                            pointRadius: currentView === 'year' ? 4 : 2,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, grid: { color: 'rgba(201,168,76,0.05)' }, ticks: { color: '#7A6E5E' } },
                            x: { grid: { display: false }, ticks: { color: '#7A6E5E', maxTicksLimit: 15 } }
                        }
                    }
                });
            }

            if (weeklyCtx) {
                window.weeklyChartInstance = new Chart(weeklyCtx, {
                    type: 'bar',
                    data: {
                        labels: currentView === 'year' ? ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'] : ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4', 'Sem 5'],
                        datasets: [{
                            label: currentView === 'year' ? 'Commandes' : 'CA HT (€)',
                            data: weekly,
                            backgroundColor: currentView === 'year' ? '#10b981' : '#C9A84C',
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, grid: { color: 'rgba(201,168,76,0.05)' }, ticks: { color: '#7A6E5E', precision: 0 } },
                            x: { grid: { display: false }, ticks: { color: '#7A6E5E' } }
                        }
                    }
                });
            }
        }
    }
}
</script>

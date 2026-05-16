<?php
/**
 * Admin — Dashboard Transparence Salariale
 * Route: GET /admin/transparency
 *
 * Conformité : Directive Européenne sur la transparence des rémunérations.
 * Ce dashboard est accessible en LECTURE SEULE à tous les membres du staff (EnsureIsStaff).
 * Il affiche les performances du mois en cours (CA généré, volume de commandes traitées).
 */

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Order;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new 
#[Layout('layouts.app')]
#[Title('Transparence Salariale')]
class extends Component {
    public string $currentMonth;
    public array $stats = [];
    public float $totalRevenue = 0;
    public int $totalVolume = 0;

    public function mount(): void
    {
        Carbon::setLocale('fr');
        $this->currentMonth = ucfirst(Carbon::now()->translatedFormat('F Y'));
        
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // Récupérer tout le staff
        $staff = User::whereIn('role', ['super-admin', 'operator', 'marketing'])->get();

        foreach ($staff as $user) {
            // Commandes assignées à cet opérateur, marquées comme LIVRÉES ce mois-ci
            $orders = Order::where('operator_id', $user->id)
                ->where('status', 'DELIVERED')
                ->whereBetween('delivered_at', [$startOfMonth, $endOfMonth])
                ->get();

            // Le CA est basé sur le TTC net facturé au client
            $revenue = $orders->sum('total_price_cents') / 100;
            $volume = $orders->count();
            
            $this->stats[] = [
                'id'      => $user->id,
                'name'    => $user->name,
                'email'   => $user->email,
                'role'    => $user->role,
                'volume'  => $volume,
                'revenue' => $revenue
            ];

            $this->totalRevenue += $revenue;
            $this->totalVolume  += $volume;
        }

        // Trier par chiffre d'affaires (décroissant)
        usort($this->stats, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
    }
};
?>

<div>
    <div class="flex items-center justify-between mb-8">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-[#F5F0E8]">Transparence Salariale</h1>
                <span class="px-2.5 py-1 text-xs border border-blue-500/30 bg-blue-900/40 text-blue-400 rounded-full">
                    Loi Européenne (UE)
                </span>
            </div>
            <p class="text-[#7A6E5E] text-sm mt-1">
                Performances et chiffre d'affaires généré par l'équipe pour le mois de <strong class="text-[#C9A84C]">{{ $currentMonth }}</strong>.
            </p>
        </div>
        <div class="text-right">
            <p class="text-[#7A6E5E] text-xs uppercase tracking-widest mb-1">CA Équipe ce mois</p>
            <p class="text-3xl font-bold text-[#C9A84C]">{{ number_format($totalRevenue, 2, ',', ' ') }} €</p>
            <p class="text-[#7A6E5E] text-sm">{{ $totalVolume }} commande{{ $totalVolume > 1 ? 's' : '' }} livrée{{ $totalVolume > 1 ? 's' : '' }}</p>
        </div>
    </div>

    {{-- Note explicative --}}
    <div class="mb-8 p-4 bg-[#1A1510] border border-[#C9A84C]/20 rounded-sm flex gap-4 items-start shadow-xl shadow-black/20">
        <div class="shrink-0 w-10 h-10 rounded-full bg-[#C9A84C]/10 border border-[#C9A84C]/30 flex items-center justify-center text-[#C9A84C]">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div>
            <h3 class="text-[#F5F0E8] text-sm font-semibold mb-1">Pourquoi cette page ?</h3>
            <p class="text-[#7A6E5E] text-sm leading-relaxed">
                Conformément à la nouvelle Directive Européenne sur la transparence des rémunérations, OmnyRestore s'engage à garantir un accès équitable et clair aux données de performance de tous ses collaborateurs. Cette page affiche les revenus TTC générés par chaque membre de l'équipe sur les commandes finalisées.
            </p>
        </div>
    </div>

    {{-- Tableau des performances --}}
    <div class="card-glass overflow-hidden">
        <div class="px-6 py-4 border-b border-[#C9A84C]/10 bg-[#1A1510]/50 flex justify-between items-center">
            <h2 class="text-[#F5F0E8] font-semibold text-sm">Classement des Opérateurs</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#1A1510]/80 border-b border-[#C9A84C]/10">
                        <th class="px-6 py-4 text-[#7A6E5E] font-medium text-xs uppercase tracking-wider w-12">#</th>
                        <th class="px-6 py-4 text-[#7A6E5E] font-medium text-xs uppercase tracking-wider">Collaborateur</th>
                        <th class="px-6 py-4 text-[#7A6E5E] font-medium text-xs uppercase tracking-wider text-center">Rôle</th>
                        <th class="px-6 py-4 text-[#7A6E5E] font-medium text-xs uppercase tracking-wider text-center">Volume</th>
                        <th class="px-6 py-4 text-[#7A6E5E] font-medium text-xs uppercase tracking-wider text-right">CA Généré (TTC)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#C9A84C]/5">
                    @forelse ($stats as $index => $stat)
                    <tr class="hover:bg-[#C9A84C]/5 transition-colors {{ auth()->id() === $stat['id'] ? 'bg-blue-900/10' : '' }}">
                        <td class="px-6 py-4 text-[#7A6E5E] text-sm">
                            @if ($index === 0 && $stat['revenue'] > 0)
                                🥇
                            @elseif ($index === 1 && $stat['revenue'] > 0)
                                🥈
                            @elseif ($index === 2 && $stat['revenue'] > 0)
                                🥉
                            @else
                                {{ $index + 1 }}
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#2A241C] to-[#1A1510] border border-[#C9A84C]/20 flex items-center justify-center text-[#C9A84C] font-bold text-xs uppercase shadow-inner">
                                    {{ substr($stat['name'], 0, 2) }}
                                </div>
                                <div>
                                    <div class="text-[#F5F0E8] font-medium text-sm flex items-center gap-2">
                                        {{ $stat['name'] }}
                                        @if(auth()->id() === $stat['id'])
                                            <span class="px-1.5 py-0.5 bg-blue-500/20 text-blue-400 text-[10px] rounded-sm border border-blue-500/30">VOUS</span>
                                        @endif
                                    </div>
                                    <div class="text-[#7A6E5E] text-xs">{{ $stat['email'] }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @php
                                $roleColors = [
                                    'super-admin' => 'bg-red-500/10 text-red-400 border-red-500/30',
                                    'operator'    => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                                    'marketing'   => 'bg-purple-500/10 text-purple-400 border-purple-500/30',
                                ];
                                $roleLabels = [
                                    'super-admin' => 'Super Admin',
                                    'operator'    => 'Opérateur',
                                    'marketing'   => 'Marketing',
                                ];
                            @endphp
                            <span class="px-2 py-1 text-[10px] uppercase font-bold tracking-wider rounded border {{ $roleColors[$stat['role']] ?? 'bg-gray-800 text-gray-400' }}">
                                {{ $roleLabels[$stat['role']] ?? $stat['role'] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-[#F5F0E8] font-medium">{{ $stat['volume'] }}</span>
                            <span class="text-[#7A6E5E] text-xs ml-1">cmd{{ $stat['volume'] > 1 ? 's' : '' }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="text-[#C9A84C] font-bold text-lg">
                                {{ number_format($stat['revenue'], 2, ',', ' ') }} €
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-[#7A6E5E]">
                            <svg class="w-12 h-12 mx-auto mb-4 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p>Aucun collaborateur actif ce mois-ci.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

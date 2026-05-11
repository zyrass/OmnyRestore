<?php
/**
 * Admin — Liste de toutes les commandes
 * Route: GET /admin/orders
 * Middleware: auth, verified, admin
 *
 * Affiche toutes les commandes avec filtres par statut et recherche.
 * Fonctionnalités Livewire :
 *   - Filtre par statut en temps réel (wire:model.live)
 *   - Recherche par référence ou email client
 *   - Pagination
 */

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Commandes — Admin')]
class extends Component
{
    use WithPagination;

    /** Filtre statut — synced with URL ?status= */
    #[Url]
    public string $status = '';

    /** Recherche libre (référence ou nom client) */
    #[Url]
    public string $search = '';

    public function with(): array
    {
        return [
            'orders' => Order::with('user')
                ->when($this->status, fn($q) => $q->where('status', $this->status))
                ->when($this->search, function ($q) {
                    $q->where(function ($q) {
                        $q->where('reference', 'ilike', "%{$this->search}%")
                          ->orWhereHas('user', fn($u) => $u->where('name', 'ilike', "%{$this->search}%")
                              ->orWhere('email', 'ilike', "%{$this->search}%"));
                    });
                })
                ->latest()
                ->paginate(20),

            'counts' => [
                'all'         => Order::count(),
                'PENDING'     => Order::where('status', 'PENDING')->count(),
                'IN_PROGRESS' => Order::where('status', 'IN_PROGRESS')->count(),
                'DONE'        => Order::where('status', 'DONE')->count(),
                'PAID'        => Order::where('payment_status', 'paid')->count(),
                'CANCELLED'   => Order::where('status', 'CANCELLED')->count(),
            ],
        ];
    }

    public function updatedStatus(): void { $this->resetPage(); }
    public function updatedSearch(): void { $this->resetPage(); }
}; ?>

<div>
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-[#F5F0E8]">Commandes</h1>
            <p class="text-[#7A6E5E] text-sm mt-1">{{ $counts['all'] }} commande{{ $counts['all'] > 1 ? 's' : '' }} au total</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" wire:navigate class="text-[#7A6E5E] hover:text-[#C9A84C] transition-colors text-sm flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Dashboard
        </a>
    </div>

    {{-- Filtres --}}
    <div class="card-glass p-4 mb-6 flex flex-col sm:flex-row gap-4">
        {{-- Recherche --}}
        <div class="flex-1 relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#7A6E5E]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input wire:model.live.debounce.300ms="search" type="text"
                   placeholder="Chercher une référence, un client…"
                   class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm pl-9 pr-4 py-2.5
                          placeholder-[#7A6E5E]/50 focus:outline-none focus:border-[#C9A84C]/60 focus:ring-1 focus:ring-[#C9A84C]/30 transition-all">
        </div>

        {{-- Filtre statut --}}
        <div class="flex flex-wrap gap-2">
            @foreach([
                ['value' => '',           'label' => 'Tous',        'count' => $counts['all']],
                ['value' => 'PENDING',    'label' => 'En attente',  'count' => $counts['PENDING']],
                ['value' => 'IN_PROGRESS','label' => 'En cours',    'count' => $counts['IN_PROGRESS']],
                ['value' => 'DONE',       'label' => 'Prêts',       'count' => $counts['DONE']],
                ['value' => 'CANCELLED',  'label' => 'Annulés',     'count' => $counts['CANCELLED']],
            ] as $filter)
            <button wire:click="$set('status', '{{ $filter['value'] }}')"
                    class="px-3 py-1.5 text-xs rounded-sm border transition-all
                           {{ $status === $filter['value']
                               ? 'border-[#C9A84C]/60 bg-[#C9A84C]/15 text-[#C9A84C]'
                               : 'border-[#C9A84C]/15 text-[#7A6E5E] hover:border-[#C9A84C]/35 hover:text-[#F5F0E8]' }}">
                {{ $filter['label'] }}
                @if ($filter['count'] > 0)
                <span class="ml-1 opacity-70">{{ $filter['count'] }}</span>
                @endif
            </button>
            @endforeach
        </div>
    </div>

    {{-- Table --}}
    <div class="card-glass overflow-hidden">
        @if ($orders->isEmpty())
        <div class="px-5 py-16 text-center">
            <svg class="w-10 h-10 text-[#C9A84C]/20 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <p class="text-[#7A6E5E] text-sm">Aucune commande trouvée</p>
        </div>
        @else
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[#C9A84C]/10">
                    @foreach(['Référence', 'Client', 'Photos', 'Statut', 'IA', 'Montant', 'Date', ''] as $h)
                    <th class="text-left text-[#7A6E5E] text-xs tracking-widest uppercase px-5 py-4 font-medium
                               {{ in_array($h, ['IA', 'Montant']) ? 'hidden lg:table-cell' : '' }}
                               {{ $h === '' ? '' : '' }}">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-[#C9A84C]/8">
                @foreach ($orders as $order)
                @php
                    $badges = [
                        'PENDING'     => 'bg-yellow-900/40 text-yellow-400 border-yellow-500/30',
                        'IN_PROGRESS' => 'bg-blue-900/40 text-blue-400 border-blue-500/30',
                        'DONE'        => 'bg-[#C9A84C]/15 text-[#C9A84C] border-[#C9A84C]/30',
                        'PAID'        => 'bg-emerald-900/40 text-emerald-400 border-emerald-500/30',
                        'DELIVERED'   => 'bg-emerald-900/60 text-emerald-300 border-emerald-400/40',
                        'CANCELLED'   => 'bg-red-900/30 text-red-400 border-red-500/30',
                    ];
                    $labels = ['PENDING' => 'En attente', 'IN_PROGRESS' => 'En cours', 'DONE' => 'Prêt', 'PAID' => 'Payé ✓', 'DELIVERED' => 'Livré', 'CANCELLED' => 'Annulé'];
                @endphp
                <tr class="hover:bg-[#C9A84C]/3 transition-colors cursor-pointer" wire:click="$navigate('{{ route('admin.orders.show', $order) }}')">
                    <td class="px-5 py-3.5"><span class="font-mono text-[#C9A84C] text-xs">{{ $order->reference }}</span></td>
                    <td class="px-5 py-3.5">
                        <p class="text-[#F5F0E8] text-xs">{{ $order->user->name }}</p>
                        <p class="text-[#7A6E5E] text-[10px]">{{ $order->user->email }}</p>
                    </td>
                    <td class="px-5 py-3.5 text-[#7A6E5E]">{{ $order->photo_count }}</td>
                    <td class="px-5 py-3.5">
                        <span class="inline-flex px-2 py-0.5 text-[10px] font-medium border rounded-full {{ $badges[$order->status] ?? 'bg-gray-900/40 text-gray-400 border-gray-500/30' }}">
                            {{ $labels[$order->status] ?? $order->status }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 hidden lg:table-cell">
                        <span class="text-[10px] {{ $order->damage_level === 'heavy' ? 'text-orange-400' : 'text-emerald-400' }}">
                            {{ $order->damage_level === 'heavy' ? '⚠ Avancée' : '✓ Standard' }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-[#F5F0E8] hidden lg:table-cell">
                        @if ($order->total_price_cents ?? $order->base_price_cents)
                            {{ number_format(($order->total_price_cents ?? $order->base_price_cents) / 100, 2, ',', ' ') }} €
                        @else
                            <span class="text-[#7A6E5E]">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-[#7A6E5E] text-xs">{{ $order->created_at->format('d/m/Y') }}</td>
                    <td class="px-5 py-3.5 text-right">
                        <svg class="w-4 h-4 text-[#7A6E5E]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Pagination --}}
        @if ($orders->hasPages())
        <div class="px-5 py-4 border-t border-[#C9A84C]/10">
            {{ $orders->links() }}
        </div>
        @endif
        @endif
    </div>
</div>

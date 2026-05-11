<?php
/**
 * Client — Liste des commandes
 * Route: GET /client/orders
 * Middleware: auth, verified
 *
 * Affiche toutes les commandes de l'utilisateur connecté, triées par date.
 * Chaque commande affiche son statut avec un badge coloré et les actions disponibles.
 */

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Mes commandes')]
class extends Component
{
    /**
     * Charger les commandes de l'utilisateur connecté.
     * with() pré-charge la relation delivery pour éviter les N+1 queries.
     */
    public function with(): array
    {
        return [
            'orders' => Order::where('user_id', auth()->id())
                ->with('delivery')
                ->latest()
                ->get(),
        ];
    }
}; ?>

<div>
    {{-- En-tête de page --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-[#F5F0E8]">Mes commandes</h1>
            <p class="text-[#7A6E5E] text-sm mt-1">{{ $orders->count() }} commande{{ $orders->count() > 1 ? 's' : '' }}</p>
        </div>
        <a href="{{ route('client.orders.create') }}" wire:navigate class="btn-gold">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle commande
        </a>
    </div>

    {{-- Liste vide --}}
    @if ($orders->isEmpty())
    <div class="card-glass p-16 text-center">
        <svg class="w-16 h-16 text-[#C9A84C]/20 mx-auto mb-4" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
        <h3 class="text-[#F5F0E8] font-semibold mb-2">Aucune commande pour l'instant</h3>
        <p class="text-[#7A6E5E] text-sm mb-8 max-w-sm mx-auto">Déposez vos premières photos à restaurer et découvrez le résultat avant de payer.</p>
        <a href="{{ route('client.orders.create') }}" wire:navigate class="btn-gold">
            Déposer mes premières photos
        </a>
    </div>
    @else

    {{-- Table des commandes --}}
    <div class="card-glass overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[#C9A84C]/10">
                    <th class="text-left text-[#7A6E5E] text-xs tracking-widest uppercase px-6 py-4 font-medium">Référence</th>
                    <th class="text-left text-[#7A6E5E] text-xs tracking-widest uppercase px-6 py-4 font-medium hidden md:table-cell">Photos</th>
                    <th class="text-left text-[#7A6E5E] text-xs tracking-widest uppercase px-6 py-4 font-medium">Statut</th>
                    <th class="text-left text-[#7A6E5E] text-xs tracking-widests uppercase px-6 py-4 font-medium hidden lg:table-cell">Montant</th>
                    <th class="text-left text-[#7A6E5E] text-xs tracking-widest uppercase px-6 py-4 font-medium hidden lg:table-cell">Date</th>
                    <th class="px-6 py-4"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#C9A84C]/8">
                @foreach ($orders as $order)
                <tr class="hover:bg-[#C9A84C]/3 transition-colors group">
                    {{-- Référence --}}
                    <td class="px-6 py-4">
                        <span class="font-mono text-[#C9A84C] text-xs">{{ $order->reference }}</span>
                    </td>

                    {{-- Nb photos --}}
                    <td class="px-6 py-4 text-[#7A6E5E] hidden md:table-cell">
                        {{ $order->photo_count }} photo{{ $order->photo_count > 1 ? 's' : '' }}
                    </td>

                    {{-- Badge statut --}}
                    <td class="px-6 py-4">
                        @php
                            $badges = [
                                'PENDING'     => ['text' => 'En attente',    'class' => 'bg-yellow-900/40 text-yellow-400 border-yellow-500/30'],
                                'IN_PROGRESS' => ['text' => 'En cours',      'class' => 'bg-blue-900/40 text-blue-400 border-blue-500/30'],
                                'DONE'        => ['text' => 'Aperçu prêt',   'class' => 'bg-[#C9A84C]/15 text-[#C9A84C] border-[#C9A84C]/30'],
                                'PAID'        => ['text' => 'Payé ✓',        'class' => 'bg-emerald-900/40 text-emerald-400 border-emerald-500/30'],
                                'DELIVERED'   => ['text' => 'Livré',         'class' => 'bg-emerald-900/60 text-emerald-300 border-emerald-400/40'],
                                'CANCELLED'   => ['text' => 'Annulé',        'class' => 'bg-red-900/30 text-red-400 border-red-500/30'],
                            ];
                            $badge = $badges[$order->status] ?? ['text' => $order->status, 'class' => 'bg-gray-900/40 text-gray-400 border-gray-500/30'];
                        @endphp
                        <span class="inline-flex px-2.5 py-1 text-xs font-medium border rounded-full {{ $badge['class'] }}">
                            {{ $badge['text'] }}
                        </span>
                    </td>

                    {{-- Montant --}}
                    <td class="px-6 py-4 text-[#F5F0E8] hidden lg:table-cell">
                        @if ($order->total_price_cents)
                            {{ number_format($order->total_price_cents / 100, 2, ',', ' ') }} €
                        @else
                            <span class="text-[#7A6E5E]">—</span>
                        @endif
                    </td>

                    {{-- Date --}}
                    <td class="px-6 py-4 text-[#7A6E5E] text-xs hidden lg:table-cell">
                        {{ $order->created_at->format('d/m/Y') }}
                    </td>

                    {{-- Actions --}}
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            {{-- Voir / Aperçu --}}
                            <a href="{{ route('client.orders.show', $order) }}" wire:navigate
                               class="px-3 py-1.5 text-xs border border-[#C9A84C]/25 text-[#C9A84C] hover:border-[#C9A84C]/60 hover:bg-[#C9A84C]/10 rounded-sm transition-all">
                                Voir
                            </a>

                            {{-- Télécharger (si livré et payé) --}}
                            @if (in_array($order->status, ['PAID', 'DELIVERED']) && $order->delivery?->zip_path)
                            <a href="{{ route('client.orders.download', $order) }}"
                               class="px-3 py-1.5 text-xs bg-[#C9A84C] text-[#0D0B08] font-semibold hover:bg-[#E8C97A] rounded-sm transition-all">
                                ↓ ZIP
                            </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

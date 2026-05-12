<?php
/**
 * Admin — Liste des clients
 * Route: GET /admin/clients
 * Middleware: auth, verified, admin
 */

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Clients — Admin')]
class extends Component
{
    public string $search = '';

    public function with(): array
    {
        return [
            'clients' => User::where('role', 'client')
                ->withCount('orders')
                ->withSum(
                    ['orders as total_spent_cents' => fn($q) => $q->where('payment_status', 'paid')],
                    'total_price_cents'
                )
                ->when($this->search, fn($q) =>
                    $q->where(fn($q2) =>
                        $q2->where('name', 'like', "%{$this->search}%")
                           ->orWhere('email', 'like', "%{$this->search}%")
                    )
                )
                ->latest()
                ->get(),
        ];
    }
}; ?>

<div>
    {{-- En-tête --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-[#F5F0E8]">Clients</h1>
            <p class="text-[#7A6E5E] text-sm mt-1">Gestion de la base clients</p>
        </div>
        <a href="{{ route('admin.revenue') }}" wire:navigate
           class="inline-flex items-center gap-2 px-4 py-2 text-sm border border-[#C9A84C]/30 text-[#C9A84C] hover:bg-[#C9A84C]/10 rounded-sm transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            Accéder au CA
        </a>
    </div>

    {{-- Recherche --}}
    <div class="mb-5">
        <input wire:model.live.debounce.300ms="search"
               type="search"
               placeholder="Rechercher un client (nom, email)…"
               class="w-full max-w-md bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] placeholder-[#7A6E5E]/50 rounded-sm px-4 py-2.5 text-sm focus:outline-none focus:border-[#C9A84C]/50 transition-all">
    </div>

    {{-- Tableau --}}
    <div class="card-glass overflow-hidden">
        @if ($clients->isEmpty())
        <div class="px-5 py-16 text-center">
            <p class="text-[#7A6E5E] text-sm">{{ $search ? "Aucun client trouvé pour « {$search} »." : 'Aucun client enregistré.' }}</p>
        </div>
        @else
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[#C9A84C]/10">
                    <th class="px-5 py-3 text-left text-xs text-[#7A6E5E] uppercase tracking-widest">Client</th>
                    <th class="px-4 py-3 text-center text-xs text-[#7A6E5E] uppercase tracking-widest">Commandes</th>
                    <th class="px-4 py-3 text-right text-xs text-[#7A6E5E] uppercase tracking-widest">CA payé HT</th>
                    <th class="px-4 py-3 text-right text-xs text-[#7A6E5E] uppercase tracking-widest hidden md:table-cell">Inscription</th>
                    <th class="px-4 py-3 text-right text-xs text-[#7A6E5E] uppercase tracking-widest">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#C9A84C]/5">
                @foreach ($clients as $client)
                <tr class="hover:bg-[#C9A84C]/3 transition-colors">
                    {{-- Identité --}}
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full border border-[#C9A84C]/30 bg-[#1A1510] text-[#C9A84C] text-xs font-bold flex items-center justify-center shrink-0">
                                {{ strtoupper(substr($client->name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="text-[#F5F0E8] font-medium">{{ $client->name }}</div>
                                <div class="text-[#7A6E5E] text-xs">{{ $client->email }}</div>
                            </div>
                        </div>
                    </td>

                    {{-- Nb commandes --}}
                    <td class="px-4 py-3.5 text-center">
                        <span class="text-[#F5F0E8] font-semibold">{{ $client->orders_count }}</span>
                    </td>

                    {{-- CA payé HT --}}
                    <td class="px-4 py-3.5 text-right">
                        <span class="text-[#C9A84C] font-semibold">
                            {{ number_format(($client->total_spent_cents ?? 0) / 100, 2, ',', ' ') }} €
                        </span>
                    </td>

                    {{-- Inscription --}}
                    <td class="px-4 py-3.5 text-right text-[#7A6E5E] text-xs hidden md:table-cell">
                        {{ $client->created_at->format('d/m/Y') }}
                    </td>

                    {{-- Actions --}}
                    <td class="px-4 py-3.5 text-right">
                        <a href="{{ route('admin.orders.index') }}?search={{ urlencode($client->email) }}"
                           wire:navigate
                           class="text-xs text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
                            Voir commandes →
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-5 py-3 border-t border-[#C9A84C]/10 text-[#7A6E5E] text-xs">
            {{ $clients->count() }} client{{ $clients->count() > 1 ? 's' : '' }} {{ $search ? 'trouvé' . ($clients->count() > 1 ? 's' : '') : 'au total' }}
        </div>
        @endif
    </div>
</div>

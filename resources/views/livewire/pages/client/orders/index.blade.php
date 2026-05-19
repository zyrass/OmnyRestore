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
     * Charger les commandes de l'utilisateur connecté et ses avantages fidélité.
     */
    public function with(): array
    {
        $user = auth()->user();
        
        // Auto-correction de fidélité à chaque affichage de l'index client
        if ($user) {
            app(\App\Services\LoyaltyService::class)->checkAndReward($user);
        }

        return [
            'orders' => $user ? Order::where('user_id', $user->id)
                ->with('delivery')
                ->latest()
                ->get() : collect(),
            'loyaltyProgress' => $user ? $user->loyaltyProgress() : 0,
            'eligibleOrdersCount' => $user ? $user->eligibleOrdersCount() : 0,
            'availableCoupons' => $user ? app(\App\Services\LoyaltyService::class)->getAvailableCoupons($user) : collect(),
            'loyaltyHistory' => $user ? $user->coupons()->where('is_loyalty', true)->latest()->get() : collect(),
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

    {{-- SECTION FIDÉLITÉ PREMIUM --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {{-- Jauge de Fidélité --}}
        <div class="lg:col-span-2 card-glass p-6 flex flex-col justify-between relative overflow-hidden group">
            {{-- Effet de fond or brillant --}}
            <div class="absolute -right-16 -top-16 w-36 h-36 rounded-full bg-[#C9A84C]/5 blur-3xl group-hover:bg-[#C9A84C]/10 transition-all duration-700"></div>
            
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-xs font-semibold tracking-widest text-[#C9A84C] uppercase">Programme Privilège</span>
                    <span class="px-2 py-0.5 text-[10px] bg-[#C9A84C]/10 border border-[#C9A84C]/20 rounded-full text-[#C9A84C]">Actif</span>
                </div>
                <h3 class="text-lg font-bold text-[#F5F0E8] font-serif">
                    @if ($loyaltyProgress == 0)
                        Démarrer votre cycle privilège
                    @elseif ($loyaltyProgress == 1)
                        1 commande éligible validée !
                    @elseif ($loyaltyProgress == 2)
                        Encore 1 étape avant votre cadeau !
                    @endif
                </h3>
                <p class="text-[#7A6E5E] text-xs mt-1 max-w-md">
                    @if ($loyaltyProgress == 0)
                        Effectuez votre première commande d'un minimum de 10 € TTC pour débuter votre jauge de fidélité.
                    @elseif ($loyaltyProgress == 1)
                        Superbe ! Effectuez 2 commandes supplémentaires d'un minimum de 10 € TTC pour débloquer votre réduction de 50 %.
                    @elseif ($loyaltyProgress == 2)
                        Plus qu'une seule commande éligible (min. 10 € TTC) et vous obtiendrez votre Golden Ticket de 50 % de remise !
                    @endif
                </p>
            </div>

            {{-- Cercles de Progression --}}
            <div class="flex flex-wrap items-center gap-6 mt-6">
                <div class="flex items-center gap-2">
                    {{-- Étape 1 --}}
                    <div class="w-10 h-10 rounded-full flex items-center justify-center border font-bold text-sm shadow-lg transition-all {{ $loyaltyProgress >= 1 ? 'bg-[#C9A84C] text-[#0D0B08] border-[#C9A84C] shadow-[#C9A84C]/10' : 'border-[#C9A84C]/20 text-[#7A6E5E] bg-[#1A1510]/50' }}">
                        @if ($loyaltyProgress >= 1) ✓ @else 1 @endif
                    </div>
                    
                    {{-- Liaison 1 --}}
                    <div class="w-10 h-0.5 {{ $loyaltyProgress >= 2 ? 'bg-[#C9A84C]' : 'bg-[#C9A84C]/10' }}"></div>
                    
                    {{-- Étape 2 --}}
                    <div class="w-10 h-10 rounded-full flex items-center justify-center border font-bold text-sm shadow-lg transition-all {{ $loyaltyProgress >= 2 ? 'bg-[#C9A84C] text-[#0D0B08] border-[#C9A84C] shadow-[#C9A84C]/10' : 'border-[#C9A84C]/20 text-[#7A6E5E] bg-[#1A1510]/50' }}">
                        @if ($loyaltyProgress >= 2) ✓ @else 2 @endif
                    </div>
                    
                    {{-- Liaison 2 --}}
                    <div class="w-10 h-0.5 bg-[#C9A84C]/10"></div>
                    
                    {{-- Cadeau final --}}
                    <div class="w-10 h-10 rounded-full flex items-center justify-center border border-dashed font-bold text-base transition-all {{ $loyaltyProgress == 0 ? 'border-[#C9A84C]/25 text-[#7A6E5E] bg-[#1A1510]/20' : 'border-[#C9A84C]/60 text-[#C9A84C] bg-[#C9A84C]/5 animate-pulse shadow-md shadow-[#C9A84C]/5' }}">
                        🎁
                    </div>
                </div>

                <div class="text-[11px] text-[#7A6E5E]">
                    <span class="text-[#C9A84C] font-semibold">{{ $eligibleOrdersCount }}</span> commande{{ $eligibleOrdersCount > 1 ? 's' : '' }} éligible{{ $eligibleOrdersCount > 1 ? 's' : '' }} au total.
                </div>
            </div>
        </div>

        {{-- Portefeuille de Coupons --}}
        <div class="card-glass p-6 flex flex-col justify-between relative overflow-hidden group">
            <div class="absolute -right-16 -bottom-16 w-36 h-36 rounded-full bg-[#C9A84C]/2 blur-3xl"></div>
            
            <div>
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-semibold tracking-widest text-[#C9A84C] uppercase">Mes Golden Tickets</span>
                    <span class="text-xs font-semibold text-[#C9A84C]/80">{{ $availableCoupons->count() }} disponible{{ $availableCoupons->count() > 1 ? 's' : '' }}</span>
                </div>

                @if ($availableCoupons->isEmpty())
                    <div class="flex flex-col items-center justify-center text-center py-6">
                        <svg class="w-10 h-10 text-[#7A6E5E]/30 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                        <p class="text-[#7A6E5E] text-xs">Aucun bon disponible pour l'instant.</p>
                        <p class="text-[10px] text-[#7A6E5E]/60 mt-1 font-serif">Complétez votre cycle pour débloquer votre premier bon d'achat !</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($availableCoupons as $coupon)
                        <div class="relative bg-[#0F0C08] border border-[#C9A84C]/30 hover:border-[#C9A84C]/60 rounded p-3 flex items-center justify-between shadow-md transition-all group/ticket">
                            {{-- Ligne pointillée style ticket --}}
                            <div class="absolute left-20 top-0 bottom-0 border-l border-dashed border-[#C9A84C]/20"></div>
                            
                            {{-- Partie gauche : Remise --}}
                            <div class="pr-4 z-10 flex flex-col justify-center">
                                <span class="text-2xl font-black font-serif text-[#C9A84C] leading-none">-50%</span>
                                <span class="text-[9px] text-[#7A6E5E] uppercase tracking-wider mt-1">Cadeau</span>
                            </div>

                            {{-- Partie droite : Info et code --}}
                            <div class="pl-4 flex-1 text-right z-10">
                                <div class="text-[11px] font-mono text-[#F5F0E8] font-bold select-all cursor-pointer group-hover/ticket:text-[#C9A84C] transition-colors" title="Cliquer pour copier">
                                    {{ $coupon->code }}
                                </div>
                                <div class="text-[9px] text-[#7A6E5E] mt-1">
                                    Expire le {{ $coupon->expires_at->format('d/m/Y') }}
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Historique des réductions utilisées --}}
            @if ($loyaltyHistory->isNotEmpty())
            <div class="mt-4 pt-3 border-t border-[#C9A84C]/10" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center justify-between w-full text-[10px] text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
                    <span>Historique des bons</span>
                    <svg class="w-3 h-3 transform transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-collapse class="mt-2 space-y-1.5 max-h-24 overflow-y-auto pr-1">
                    @foreach ($loyaltyHistory as $item)
                        <div class="flex items-center justify-between text-[10px] bg-[#1A1510]/30 px-2 py-1 rounded">
                            <span class="font-mono text-[#7A6E5E]/80">{{ $item->code }}</span>
                            @if ($item->used_count >= 1)
                                <span class="text-emerald-400/80 font-medium">Utilisé ✓</span>
                            @else
                                <span class="text-red-400/60 line-through">Expiré ✗</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
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

                    {{-- Montant : prévu (gris) + payé (blanc) si réglé --}}
                    <td class="px-6 py-4 hidden lg:table-cell">
                        @php
                            $mTtc = $order->getAmountTtcCents();
                            $isPaid = in_array($order->status, ['PAID', 'DELIVERED']);
                        @endphp

                        @if ($mTtc === null)
                            {{-- Prix inconnu (commande très ancienne ou pas encore évalué) --}}
                            <span class="text-[#7A6E5E]">—</span>
                        @else
                            <div class="flex flex-col gap-0.5">
                                {{-- Ligne 1 : montant prévu (gris, petit) --}}
                                <span class="text-[#7A6E5E] text-xs">
                                    Prévu : {{ number_format($mTtc / 100, 2, ',', ' ') }} €
                                </span>
                                {{-- Ligne 2 : montant payé (blanc, affiché seulement si réglé) --}}
                                @if ($isPaid)
                                <span class="text-emerald-400 font-semibold text-sm">
                                    ✓ {{ number_format($mTtc / 100, 2, ',', ' ') }} € payé
                                </span>
                                @endif
                            </div>
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

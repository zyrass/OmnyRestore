<?php
/**
 * Client — Page de confirmation de paiement
 * Route: GET /client/orders/{order}/payment-success
 * Middleware: auth, verified, client
 *
 * Affiché immédiatement après le retour Stripe.
 * - Confirme visuellement le paiement
 * - Indique que le ZIP est en cours de préparation
 * - Poll toutes les 5s pour détecter quand DELIVERED
 *   → affiche alors le bouton de téléchargement
 */

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Paiement confirmé')]
class extends Component
{
    public Order $order;

    public function mount(Order $order): mixed
    {
        $this->authorize('view', $order);
        $this->order = $order->load(['user', 'media', 'delivery']);

        // Sécurité : cette page n'a de sens que pour une commande payée (ou livrée)
        // Si la commande n'est pas encore PAID (ex: accès direct), on redirige vers show
        if (! in_array($order->status, ['PAID', 'DELIVERED'])) {
            return redirect()->route('client.orders.show', $order);
        }

        return null;
    }

    /**
     * Polling 5s — détecte quand le GenerateOrderZipJob a terminé (PAID → DELIVERED).
     * Quand DELIVERED, dispatch un event Alpine pour afficher le bouton téléchargement.
     */
    public function pollDelivery(): void
    {
        if ($this->order->status === 'DELIVERED') {
            return; // Déjà livré, arrêt du poll
        }

        $fresh = $this->order->fresh();

        if ($fresh->status === 'DELIVERED') {
            $this->order = $fresh->load(['user', 'media', 'delivery']);
            $this->dispatch('zip-ready');
        }
    }
}; ?>

<div x-data="{ zipReady: @js($order->status === 'DELIVERED') }"
     @zip-ready.window="zipReady = true">

    {{-- En-tête page --}}
    <div class="flex items-center gap-4 mb-8">
        <a href="{{ route('client.orders.show', $order) }}" wire:navigate class="text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div class="flex-1">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-[#F5F0E8]">Commande</h1>
                <span class="font-mono text-[#C9A84C] text-sm">{{ $order->reference }}</span>
            </div>
        </div>
    </div>

    {{-- Bloc principal de confirmation --}}
    <div class="max-w-xl mx-auto">

        {{-- ── Carte confirmation ── --}}
        <div class="card-glass overflow-hidden mb-6">
            {{-- Barre verte en haut --}}
            <div class="h-1 bg-gradient-to-r from-emerald-500 to-[#C9A84C]"></div>

            <div class="p-10 text-center">

                {{-- Icône animée --}}
                <div class="relative w-24 h-24 mx-auto mb-8">
                    {{-- Cercle pulsant --}}
                    <div class="absolute inset-0 rounded-full bg-emerald-500/10 animate-ping" style="animation-duration: 2s;"></div>
                    <div class="absolute inset-0 rounded-full border-2 border-emerald-500/30"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <svg class="w-10 h-10 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>

                <h2 class="text-[#F5F0E8] text-2xl font-bold mb-3">Paiement confirmé !</h2>
                <p class="text-emerald-400 text-sm font-medium mb-6">
                    Merci pour votre confiance, {{ $order->user->name }}.
                </p>

                {{-- Récapitulatif --}}
                @php
                    $htC  = $order->total_price_cents ?? $order->base_price_cents ?? 0;
                    $ttcC = $htC + (int) round($htC * 0.20);
                @endphp
                <div class="bg-[#0F0C08]/60 border border-[#C9A84C]/15 rounded-sm px-6 py-5 mb-8 text-sm text-left space-y-3">
                    <div class="flex justify-between">
                        <span class="text-[#7A6E5E]">Référence</span>
                        <span class="font-mono text-[#C9A84C] text-xs">{{ $order->reference }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#7A6E5E]">Photos restaurées</span>
                        <span class="text-[#F5F0E8]">{{ $order->photo_count }}</span>
                    </div>
                    <div class="flex justify-between border-t border-[#C9A84C]/10 pt-3">
                        <span class="text-[#7A6E5E] font-medium">Montant réglé TTC</span>
                        <span class="text-emerald-400 font-bold text-base">{{ number_format($ttcC / 100, 2, ',', ' ') }} €</span>
                    </div>
                    @if ($order->paid_at)
                    <div class="flex justify-between">
                        <span class="text-[#7A6E5E]">Payé le</span>
                        <span class="text-[#F5F0E8] text-xs">{{ $order->paid_at->format('d/m/Y à H:i:s') }}</span>
                    </div>
                    @endif
                </div>

                {{-- ── État ZIP : en attente ── --}}
                <div x-show="!zipReady" x-cloak>
                    <div wire:poll.5000ms="pollDelivery" class="flex items-center justify-center gap-3 mb-4">
                        <div class="relative w-5 h-5 shrink-0">
                            <svg class="animate-spin w-5 h-5 text-[#C9A84C]" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </div>
                        <p class="text-[#7A6E5E] text-sm">Préparation de votre archive ZIP…</p>
                    </div>

                    <div class="bg-[#C9A84C]/6 border border-[#C9A84C]/20 rounded-sm px-5 py-4 text-center mb-6">
                        <p class="text-[#F5F0E8] text-sm font-medium mb-1">📧 Surveillez vos mails !</p>
                        <p class="text-[#7A6E5E] text-xs leading-relaxed">
                            Vous venez de recevoir un email de confirmation à
                            <span class="text-[#C9A84C]">{{ $order->user->email }}</span>.
                            <br>Un <strong class="text-[#F5F0E8]">second email</strong> vous sera envoyé dès que
                            vos photos seront prêtes à télécharger (quelques minutes).
                        </p>
                    </div>

                    <div class="flex items-center gap-2 justify-center text-xs text-[#4A3E2E]">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Cette page se met à jour automatiquement
                    </div>
                </div>

                {{-- ── État ZIP : prêt ── --}}
                <div x-show="zipReady"
                     x-transition:enter="transition ease-out duration-500"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100">

                    <div class="flex items-center justify-center gap-2 mb-5 text-emerald-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-sm font-medium">Vos photos sont prêtes !</span>
                    </div>

                    <a href="{{ route('client.orders.download', $order) }}"
                       class="btn-gold text-sm px-8 py-3 inline-flex items-center gap-2 mb-4">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Télécharger le ZIP
                    </a>

                    <a href="{{ route('client.orders.invoice', $order) }}" target="_blank"
                       class="block text-xs text-[#C9A84C]/70 hover:text-[#C9A84C] transition-colors mt-2">
                        Télécharger la facture PDF →
                    </a>
                </div>

            </div>
        </div>

        {{-- ── Actions secondaires ── --}}
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('client.orders.show', $order) }}" wire:navigate
               class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm text-[#7A6E5E] border border-[#7A6E5E]/30 rounded-sm hover:border-[#C9A84C]/40 hover:text-[#F5F0E8] transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Voir la commande
            </a>
            <a href="{{ route('client.orders.index') }}" wire:navigate
               class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm text-[#7A6E5E] border border-[#7A6E5E]/30 rounded-sm hover:border-[#C9A84C]/40 hover:text-[#F5F0E8] transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Mes commandes
            </a>
        </div>

    </div>
</div>

<?php
/**
 * Client — Détail d'une commande + Aperçu filigranné
 * Route: GET /client/orders/{order}
 * Middleware: auth, verified
 *
 * Cette page est le cœur du tunnel de conversion :
 *   - Si statut PENDING/IN_PROGRESS → affiche "en cours de traitement"
 *   - Si statut DONE → affiche l'aperçu filigranné + bouton Payer
 *   - Si statut PAID/DELIVERED → affiche le bouton Télécharger le ZIP
 *   - Si statut CANCELLED → affiche message d'annulation
 *
 * Sécurité : l'authorization Policy est vérifiée via $this->authorize('view', $order)
 * avant de rendre la vue. Un client A ne peut pas voir la commande du client B.
 */

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Commande')]
class extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        // IDOR prevention : vérifie que la commande appartient à l'utilisateur connecté
        $this->authorize('view', $order);

        $this->order = $order->load(['media', 'delivery']);
    }
}; ?>

<div>
    {{-- En-tête --}}
    <div class="flex items-center gap-4 mb-8">
        <a href="{{ route('client.orders.index') }}" wire:navigate class="text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div class="flex-1">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-[#F5F0E8]">Commande</h1>
                <span class="font-mono text-[#C9A84C] text-sm">{{ $order->reference }}</span>
            </div>
            <p class="text-[#7A6E5E] text-sm mt-1">
                {{ $order->photo_count }} photo{{ $order->photo_count > 1 ? 's' : '' }} —
                {{ $order->created_at->format('d/m/Y à H:i') }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- ── Contenu principal ── --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- === ÉTAT : EN COURS === --}}
            @if (in_array($order->status, ['PENDING', 'IN_PROGRESS']))
            <div class="card-glass p-10 text-center">
                {{-- Spinner animé --}}
                <div class="relative w-20 h-20 mx-auto mb-6">
                    <div class="absolute inset-0 border-2 border-[#C9A84C]/20 rounded-full"></div>
                    <div class="absolute inset-0 border-t-2 border-[#C9A84C] rounded-full animate-spin"></div>
                    <svg class="absolute inset-0 m-auto w-8 h-8 text-[#C9A84C]/50" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                    </svg>
                </div>
                <h3 class="text-[#F5F0E8] text-lg font-semibold mb-2">
                    {{ $order->status === 'PENDING' ? 'Commande reçue — en file d\'attente' : 'Restauration en cours…' }}
                </h3>
                <p class="text-[#7A6E5E] text-sm max-w-sm mx-auto leading-relaxed">
                    Nos algorithmes analysent et restaurent vos photos.
                    Vous recevrez un email dès que l'aperçu sera prêt.
                </p>
                <div class="mt-6 flex justify-center gap-6 text-xs text-[#7A6E5E]">
                    <span>Délai estimé : 24-48h</span>
                    <span>·</span>
                    <span>{{ $order->photo_count }} photo{{ $order->photo_count > 1 ? 's' : '' }}</span>
                </div>
            </div>
            @endif

            {{-- === ÉTAT : APERÇU PRÊT (DONE) — Paiement requis === --}}
            @if ($order->status === 'DONE')
            <div class="card-glass overflow-hidden">
                <div class="p-5 border-b border-[#C9A84C]/10 flex items-center justify-between">
                    <h3 class="text-[#F5F0E8] font-semibold">Aperçu de votre restauration</h3>
                    <span class="text-[#C9A84C] text-xs border border-[#C9A84C]/30 px-2 py-0.5 rounded-full">
                        Filigranné — payer pour débloquer
                    </span>
                </div>

                {{-- Grille aperçus — collection watermarked (Intervention Image) ou fallback CSS --}}
                <div class="p-5 grid grid-cols-2 md:grid-cols-3 gap-4">
                    @php
                        $watermarked = $order->getMedia('watermarked');
                        $useCssFallback = $watermarked->isEmpty();
                        $previews = $useCssFallback
                            ? $order->getMedia('retouched')
                            : $watermarked;
                    @endphp
                    @forelse ($previews as $media)
                    <div class="relative aspect-square bg-[#1A1510] rounded-sm overflow-hidden border border-[#C9A84C]/10 group select-none">
                        <img src="{{ $media->getUrl() }}" alt="Aperçu restauré"
                             class="w-full h-full object-cover pointer-events-none"
                             draggable="false">
                        @if ($useCssFallback)
                        {{-- Fallback CSS watermark si le job n'a pas encore tourné --}}
                        <div class="absolute inset-0 pointer-events-none overflow-hidden"
                             style="background: repeating-linear-gradient(
                                 -45deg,
                                 transparent,
                                 transparent 60px,
                                 rgba(201,168,76,0.08) 60px,
                                 rgba(201,168,76,0.08) 62px
                             );">
                        </div>
                        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <span class="text-white/15 text-xs font-bold tracking-[0.3em] uppercase rotate-[-35deg] select-none whitespace-nowrap">
                                OmnyRestore
                            </span>
                        </div>
                        @endif
                    </div>
                    @empty
                    {{-- Placeholder si les aperçus ne sont pas encore générés --}}
                    @for ($i = 0; $i < $order->photo_count; $i++)
                    <div class="aspect-square bg-[#1A1510] border border-[#C9A84C]/10 rounded-sm flex items-center justify-center">
                        <div class="text-center">
                            <svg class="w-8 h-8 text-[#C9A84C]/20 mx-auto mb-1" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                            <span class="text-[#7A6E5E] text-xs">Aperçu {{ $i + 1 }}</span>
                        </div>
                    </div>
                    @endfor
                    @endforelse
                </div>

                {{-- Call to action paiement --}}
                <div class="p-5 border-t border-[#C9A84C]/10 bg-[#C9A84C]/5 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div>
                        <p class="text-[#F5F0E8] text-sm font-medium">Satisfait du résultat ?</p>
                        <p class="text-[#7A6E5E] text-xs">Payez pour télécharger vos photos en haute résolution, sans filigrane.</p>
                    </div>
                    <form action="{{ route('client.orders.checkout', $order) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn-gold whitespace-nowrap">
                            Payer — {{ $order->total_price_cents
                                ? number_format($order->total_price_cents / 100, 2, ',', ' ') . ' €'
                                : number_format(($order->base_price_cents ?? 0) / 100, 2, ',', ' ') . ' €' }}
                        </button>
                    </form>
                </div>
            </div>
            @endif

            {{-- === ÉTAT : PAYÉ / LIVRÉ === --}}
            @if (in_array($order->status, ['PAID', 'DELIVERED']))
            <div class="card-glass p-8 text-center border-[#C9A84C]/30">
                <div class="w-16 h-16 border border-emerald-500/40 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="text-[#F5F0E8] text-lg font-semibold mb-2">Paiement confirmé !</h3>
                <p class="text-[#7A6E5E] text-sm mb-6 max-w-sm mx-auto">
                    Vos photos restaurées sont prêtes. Téléchargez votre archive ZIP.
                    Ce lien est valable 6 mois.
                </p>
                @if ($order->zip_path)
                <a href="{{ route('client.orders.download', $order) }}"
                   class="btn-gold text-base px-10 py-4 inline-flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Télécharger le ZIP
                </a>
                @else
                <p class="text-[#7A6E5E] text-xs">
                    <svg class="w-4 h-4 inline animate-spin text-[#C9A84C] mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Préparation de votre archive en cours — vous recevrez un email dans quelques minutes.
                </p>
                @endif
            </div>
            @endif

            {{-- === ÉTAT : ANNULÉ === --}}
            @if ($order->status === 'CANCELLED')
            <div class="card-glass p-8 text-center border-red-500/20">
                <div class="w-16 h-16 border border-red-500/30 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <h3 class="text-[#F5F0E8] font-semibold mb-2">Commande annulée</h3>
                <p class="text-[#7A6E5E] text-sm mb-6">
                    Cette commande a été annulée. Aucun montant n'a été prélevé.
                </p>
                <a href="{{ route('client.orders.create') }}" wire:navigate class="btn-outline">
                    Créer une nouvelle commande
                </a>
            </div>
            @endif

        </div>

        {{-- ── Sidebar info commande ── --}}
        <div class="space-y-5">

            {{-- Statut --}}
            <div class="card-glass p-5">
                <h3 class="text-[#7A6E5E] text-xs tracking-widest uppercase mb-4">Détails</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-[#7A6E5E]">Référence</dt>
                        <dd class="font-mono text-[#C9A84C] text-xs">{{ $order->reference }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#7A6E5E]">Photos</dt>
                        <dd class="text-[#F5F0E8]">{{ $order->photo_count }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#7A6E5E]">Type</dt>
                        <dd class="text-[#F5F0E8]">{{ $order->damage_level === 'heavy' ? 'Avancée' : 'Standard' }}</dd>
                    </div>
                    @if ($order->base_price_cents)
                    <div class="flex justify-between pt-2 border-t border-[#C9A84C]/10">
                        <dt class="text-[#7A6E5E]">Montant HT</dt>
                        <dd class="text-[#C9A84C] font-semibold">
                            {{ number_format($order->base_price_cents / 100, 2, ',', ' ') }} €
                        </dd>
                    </div>
                    @endif
                    @if ($order->paid_at)
                    <div class="flex justify-between">
                        <dt class="text-[#7A6E5E]">Payé le</dt>
                        <dd class="text-emerald-400 text-xs">{{ $order->paid_at->format('d/m/Y') }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- Instructions --}}
            @if ($order->instructions)
            <div class="card-glass p-5">
                <h3 class="text-[#7A6E5E] text-xs tracking-widest uppercase mb-3">Vos instructions</h3>
                <p class="text-[#7A6E5E] text-xs leading-relaxed">{{ $order->instructions }}</p>
            </div>
            @endif

            {{-- Aide --}}
            <div class="card-glass p-5 bg-[#1A1510]/50">
                <h3 class="text-[#F5F0E8] text-sm font-medium mb-2">Une question ?</h3>
                <p class="text-[#7A6E5E] text-xs leading-relaxed mb-3">
                    Notre équipe répond sous 24h. Votre ticket sera automatiquement
                    lié à la commande <span class="font-mono text-[#C9A84C]">{{ $order->reference }}</span>.
                </p>
                <a href="{{ route('client.tickets.create') }}?order_id={{ $order->id }}"
                   wire:navigate
                   class="inline-flex items-center gap-1.5 text-[#C9A84C] text-xs hover:text-[#E8C97A] transition-colors font-medium">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    Contacter le support →
                </a>
            </div>

        </div>
    </div>
</div>

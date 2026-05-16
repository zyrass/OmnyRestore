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

use App\Http\Requests\Client\StoreTestimonialRequest;
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
        $this->authorize('view', $order);
        $this->order = $order->load(['media', 'delivery']);
    }

    /**
     * Fournit les données calculées dynamiquement pour la vue.
     */
    public function with(): array
    {
        $allPhotos = $this->order->getMedia('retouched');
        $activePhotos = $allPhotos->filter(fn($m) => ! $m->getCustomProperty('is_rejected', false));

        // On utilise les méthodes du modèle qui gèrent déjà les fallbacks et le TTC Net
        $payTtc = $this->order->getAmountTtcCents();
        $payHt  = $this->order->getAmountHtCents();
        
        // Base TTC avant remise pour l'affichage du détail
        $baseTtcC = $payTtc + ($this->order->discount_cents ?? 0);
        $baseHtC = (int) round($baseTtcC / 1.2);

        return [
            'activePhotos' => $activePhotos,
            'baseTtcC'     => $baseTtcC,
            'baseHtC'      => $baseHtC,
            'discountC'    => $this->order->discount_cents ?? 0,
            'payTtc'       => $payTtc,
            'payHt'        => $payHt,
        ];
    }

    /**
     * Renvoie l'email de déverrouillage (OrderReadyForPayment) avec une nouvelle URL signée.
     * Limité à 1 envoi toutes les 5 minutes pour éviter le spam.
     */
    public function resendUnlockEmail(): void
    {
        abort_if($this->order->user_id !== auth()->id(), 403);
        abort_if($this->order->status !== 'DONE', 403);

        // Garde-fou : 1 envoi max par 5 minutes (basé sur preview_email_resent_at stocké en session)
        $sessionKey = "resend_unlock_{$this->order->id}";
        if (session()->has($sessionKey) && now()->diffInSeconds(session($sessionKey)) < 300) {
            $remaining = 300 - now()->diffInSeconds(session($sessionKey));
            session()->flash('error', "Patientez encore {$remaining} secondes avant de renvoyer l'email.");
            return;
        }

        \Illuminate\Support\Facades\Mail::to($this->order->user->email)
            ->queue(new \App\Mail\OrderReadyForPayment($this->order));

        session()->put($sessionKey, now());
        session()->flash('success', '📧 Email renvoyé ! Vérifiez votre boîte mail (et vos spams).');
    }

    /**
     * Sondage Livewire : détecte quand le ZIP est prêt après paiement.
     * Activé via wire:poll.5000ms seulement si status=PAID et zip_path=null.
     * Quand le job GenerateOrderZipJob termine (status→DELIVERED), on rafraîchit
     * le composant et on dispatch un event Alpine 'zip-ready' pour le toast.
     */
    public function pollDelivery(): void
    {
        // Inutile de poller si on attend pas le ZIP
        if ($this->order->status !== 'PAID' || $this->order->zip_path) {
            return;
        }

        $fresh = $this->order->fresh();

        // Le ZIP vient d'être généré !
        if ($fresh->zip_path) {
            $this->order = $fresh->load(['media', 'delivery']);
            // Dispatch vers Alpine.js pour déclencher le toast
            $this->dispatch('zip-ready');
        }
    }

    /**
     * Polling 10s — détecte quand l'admin fait passer la commande à DONE.
     * Activé via wire:poll.10000ms seulement si status est PENDING ou IN_PROGRESS.
     * Quand le statut change, on dispatch 'status-ready' vers Alpine.js pour
     * afficher un toast invitant l'utilisateur à rafraîchir.
     */
    public function pollStatusChange(): void
    {
        if (! in_array($this->order->status, ['PENDING', 'IN_PROGRESS'])) {
            return;
        }

        $fresh = $this->order->fresh();

        if ($fresh->status === 'DONE') {
            $this->order = $fresh->load(['media', 'delivery']);
            $this->dispatch('status-ready');
        }
    }

    /**
     * Polling 15s — détecte quand le statut passe de DONE à PAID.
     * Utile si le client paie dans un autre onglet ou sur mobile.
     */
    public function pollPaymentStatus(): void
    {
        if ($this->order->status !== 'DONE') {
            return;
        }

        $fresh = $this->order->fresh();

        if ($fresh->status === 'PAID') {
            $this->order = $fresh->load(['media', 'delivery']);
        }
    }

    /**
     * Soumission d'un témoignage client après livraison.
     * Disponible uniquement pour les commandes DELIVERED.
     * Un seul avis par commande (contrôle en base via UNIQUE order_id).
     */
    public string $testimonialContent = '';
    public int    $testimonialRating  = 5;

    public function submitTestimonial(): void
    {
        abort_if($this->order->user_id !== auth()->id(), 403);
        abort_if($this->order->status !== 'DELIVERED', 403, 'Les avis ne sont disponibles qu\'après livraison.');

        // Règles et messages centralisés dans StoreTestimonialRequest
        $request = new StoreTestimonialRequest;
        $this->validate($request->rules(), $request->messages());

        // Idempotent : déjà soumis pour cette commande
        if (StoreTestimonialRequest::alreadySubmitted($this->order->id)) {
            session()->flash('success', 'Vous avez déjà partagé votre avis pour cette commande. Merci !');
            return;
        }

        $user = auth()->user();
        \App\Models\Testimonial::create([
            'order_id'        => $this->order->id,
            'user_id'         => $user->id,
            'author_name'     => $user->name,
            'author_initials' => \App\Models\Testimonial::initialsFrom($user->name),
            'rating'          => $this->testimonialRating,
            'content'         => $this->testimonialContent,
            'is_published'    => false, // en attente de modération admin
        ]);

        $this->dispatch('refresh-navbar-counts');

        $this->testimonialContent = '';
        $this->testimonialRating  = 5;
        session()->flash('success', '⭐ Merci pour votre avis ! Il sera visible après validation par notre équipe.');
    }

    /**
     * Rafraîchit l'état de la commande (et de la livraison) après un clic sur "Télécharger".
     * Permet la mise à jour dynamique du compteur sans rechargement complet de la page.
     */
    public function refreshAfterDownload(): void
    {
        $this->order->refresh()->load('delivery');
    }

    /**
     * Client rejette une photo restaurée (exclue du livrable et du calcul de prix).
     * Disponible uniquement au statut DONE (avant paiement).
     */
    public function rejectPhoto(int $mediaId): void
    {
        abort_if($this->order->user_id !== auth()->id(), 403);
        abort_if($this->order->status !== 'DONE', 403, 'La sélection n\'est possible qu\'avant le paiement.');

        $media = $this->order->getMedia('retouched')->firstWhere('id', $mediaId);
        abort_if(! $media, 404, 'Photo introuvable.');

        $media->setCustomProperty('is_rejected', true)
              ->setCustomProperty('rejected_at', now()->toISOString())
              ->setCustomProperty('rejected_by', 'client')
              ->save();

        $this->recalcPriceFromActivePhotos();
        session()->flash('success', 'Photo retirée de votre sélection — prix mis à jour.');
        $this->order->refresh()->load(['media', 'delivery']);
    }

    /**
     * Client réintègre une photo précédemment rejetée.
     */
    public function restorePhoto(int $mediaId): void
    {
        abort_if($this->order->user_id !== auth()->id(), 403);
        abort_if($this->order->status !== 'DONE', 403);

        $media = $this->order->getMedia('retouched')->firstWhere('id', $mediaId);
        abort_if(! $media, 404);

        $media->forgetCustomProperty('is_rejected')
              ->forgetCustomProperty('rejected_at')
              ->forgetCustomProperty('rejected_by')
              ->save();

        $this->recalcPriceFromActivePhotos();
        session()->flash('success', 'Photo réintégrée — prix mis à jour.');
        $this->order->refresh()->load(['media', 'delivery']);
    }

    /**
     * Client supprime définitivement une photo restaurée (irréversible).
     * La photo doit être préalablement retirée (is_rejected) pour activer ce droit.
     * On s'assure qu'il reste au moins 1 photo active avant de supprimer.
     * 
     * <strong>Transparence tarifaire :</strong> Le tarif est ajusté automatiquement selon le niveau de restauration détecté pour chaque photo (standard, avancé ou complet).
     * Le coût d'analyse technique est intégré au tarif global et non facturé séparément.
     */
    public function deletePhoto(int $mediaId): void
    {
        abort_if($this->order->user_id !== auth()->id(), 403);
        abort_if($this->order->status !== 'DONE', 403);

        $media = $this->order->getMedia('retouched')->firstWhere('id', $mediaId);
        abort_if(! $media, 404);
        abort_if(! $media->getCustomProperty('is_rejected', false), 403, 'Retirez la photo avant de la supprimer.');

        // Garde-fou : la commande doit garder au moins 1 photo (active ou non)
        // car on ne peut pas avoir une commande vide.
        $totalCount = $this->order->getMedia('retouched')->count();

        if ($totalCount <= 1) {
            session()->flash('error', 'Impossible de supprimer la dernière photo de la commande.');
            return;
        }

        \Illuminate\Support\Facades\Log::info(
            "Client delete media#{$mediaId} on order {$this->order->reference} by user#{auth()->id()}"
        );

        $media->delete();

        // Mise à jour du compteur total de la commande
        $this->order->decrement('photo_count');

        $this->recalcPriceFromActivePhotos();
        session()->flash('success', 'Photo supprimée définitivement de votre commande.');
        $this->order->refresh()->load(['media', 'delivery']);
    }

    /**
     * Recalcule total_price_cents d'après les photos ACTIVES (non rejetées).
     *
     * IMPORTANT : chaque photo peut avoir un niveau de dommage différent,
     * stocké dans custom_properties('ai_level'). On somme photo par photo.
     * Fallback : si ai_level absent, on utilise le damage_level de la commande.
     *
     * Cohérent avec invoice.blade.php (même logique de calcul par niveau).
     */
    private function recalcPriceFromActivePhotos(): void
    {
        $pricesTtc = \App\Services\PhotoDamageAnalyzer::PRICES_TTC;

        $activePhotos = $this->order->getMedia('retouched')
            ->filter(fn($m) => ! $m->getCustomProperty('is_rejected', false));

        // Somme par photo selon son niveau individuel TTC
        $newBaseTtc = $activePhotos->sum(function ($media) use ($pricesTtc) {
            $level = $media->getCustomProperty('ai_level', $this->order->damage_level ?? 'light');
            return $pricesTtc[$level] ?? $pricesTtc['light'];
        });

        $newDiscountTtc = 0;

        if ($this->order->coupon_code) {
            $coupon = \App\Models\Coupon::where('code', $this->order->coupon_code)->first();
            if ($coupon) {
                // Utilisation de la nouvelle logique TTC pour éviter les décalages de TVA
                $newDiscountTtc = $coupon->discountTtcCents($newBaseTtc);
            }
        }

        $newTotalTtc = max(0, $newBaseTtc - $newDiscountTtc);

        // total_price_cents est désormais le TTC net (Source de vérité)
        $this->order->update([
            'total_price_cents' => $newTotalTtc,
            'discount_cents'    => $newDiscountTtc,
        ]);

        \Illuminate\Support\Facades\Log::info(
            "Client recalc {$this->order->reference}: {$activePhotos->count()} photo(s) = {$newTotalTtc} cts TTC net.",
            ['breakdown' => $activePhotos->map(fn($m) => [
                'id'    => $m->id,
                'level' => $m->getCustomProperty('ai_level', $this->order->damage_level ?? 'light'),
                'price' => $pricesTtc[$m->getCustomProperty('ai_level', $this->order->damage_level ?? 'light')] ?? $pricesTtc['light'],
            ])->values()->toArray()]
        );
    }
}; ?>

{{--
    Toast Alpine.js — notification live « ZIP prêt »
    Déclenché par l'event Livewire 'zip-ready' (via wire:poll + pollDelivery)
--}}
<div x-data="{ showZipToast: false, showStatusToast: false, showInvoice: false }"
     @zip-ready.window="showZipToast = true"
     @status-ready.window="showStatusToast = true"
     class="contents">

    {{-- Toast positioenné en haut à droite via Teleport --}}
    <template x-teleport="body">
        <div x-show="showZipToast" x-cloak
             x-transition:enter="transition ease-out duration-500"
             x-transition:enter-start="opacity-0 translate-y-4 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0 translate-y-4"
             class="fixed bottom-6 right-6 z-[9999] max-w-sm w-full"
             style="filter: drop-shadow(0 20px 40px rgba(0,0,0,0.6));">
            <div class="relative bg-[#1A1510] border border-emerald-500/40 rounded-sm overflow-hidden">
                {{-- Barre de progression verte en haut --}}
                <div class="h-0.5 bg-gradient-to-r from-emerald-500 to-[#C9A84C]"></div>
                <div class="p-5 flex items-start gap-4">
                    {{-- Icône success animée --}}
                    <div class="shrink-0 w-10 h-10 rounded-full bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                    </div>
                    {{-- Texte --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-[#F5F0E8] text-sm font-semibold leading-tight">Votre archive est prête !</p>
                        <p class="text-[#7A6E5E] text-xs mt-1">Vos photos restaurées sont disponibles en HD sans filigrane.</p>
                        <a href="{{ route('client.orders.download', $order) }}"
                           class="inline-flex items-center gap-1.5 mt-3 px-4 py-2 text-xs font-bold text-[#0F0C08] bg-gradient-to-r from-[#C9A84C] to-[#E8C97A] rounded-sm hover:opacity-90 transition-opacity">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Télécharger le ZIP
                        </a>
                    </div>
                    {{-- Fermeture --}}
                    <button @click="showZipToast = false" class="shrink-0 text-[#7A6E5E] hover:text-[#F5F0E8] transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- Toast "Photos prêtes" — déclenché par pollStatusChange quand admin → DONE --}}
    <template x-teleport="body">
        <div x-show="showStatusToast" x-cloak
             x-transition:enter="transition ease-out duration-500"
             x-transition:enter-start="opacity-0 translate-y-4 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0 translate-y-4"
             class="fixed bottom-6 right-6 z-[9999] max-w-sm w-full"
             style="filter: drop-shadow(0 20px 40px rgba(0,0,0,0.6));">
            <div class="relative bg-[#1A1510] border border-[#C9A84C]/50 rounded-sm overflow-hidden">
                <div class="h-0.5 bg-gradient-to-r from-[#C9A84C] to-[#E8C97A]"></div>
                <div class="p-5 flex items-start gap-4">
                    <div class="shrink-0 w-10 h-10 rounded-full bg-[#C9A84C]/15 border border-[#C9A84C]/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[#F5F0E8] text-sm font-semibold leading-tight">Vos photos sont prêtes !</p>
                        <p class="text-[#7A6E5E] text-xs mt-1">L'équipe a terminé la restauration. Actualisez pour voir vos photos.</p>
                        <button onclick="window.location.reload()"
                                class="inline-flex items-center gap-1.5 mt-3 px-4 py-2 text-xs font-bold text-[#0F0C08] bg-gradient-to-r from-[#C9A84C] to-[#E8C97A] rounded-sm hover:opacity-90 transition-opacity">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Actualiser la page
                        </button>
                    </div>
                    <button @click="showStatusToast = false" class="shrink-0 text-[#7A6E5E] hover:text-[#F5F0E8] transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </template>

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

    {{-- Flash messages contextuels --}}
    @if (session('success'))
    <div class="mb-6 flex items-start gap-3 px-4 py-3.5 rounded-sm border border-emerald-500/30 bg-emerald-500/10">
        <svg class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-emerald-300 text-sm">{{ session('success') }}</p>
    </div>
    @endif
    @if (session('error'))
    <div class="mb-6 flex items-start gap-3 px-4 py-3.5 rounded-sm border border-red-500/30 bg-red-500/10">
        <svg class="w-5 h-5 text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-red-300 text-sm">{{ session('error') }}</p>
    </div>
    @endif


    @php
        $retouched      = $order->getMedia('retouched');
        $activePhotos   = $retouched->filter(fn($m) => ! $m->getCustomProperty('is_rejected', false));
        $rejectedPhotos = $retouched->filter(fn($m) => $m->getCustomProperty('is_rejected', false));
        
        // TTC net via le modèle (source de vérité)
        $ttcC = $order->getAmountTtcCents();
        $htC  = $order->getAmountHtCents();
        $tvaC = max(0, $ttcC - $htC);
        
        // Pour l'affichage du prix estimé si pas encore de retouches
        if ($ttcC === 0 && in_array($order->status, ['PENDING', 'IN_PROGRESS'])) {
             $pricesTtc = \App\Services\PhotoDamageAnalyzer::PRICES_TTC;
             $ttcC = $order->getMedia('originals')->sum(function ($m) use ($pricesTtc, $order) {
                $level = $m->getCustomProperty('ai_level', $order->damage_level ?? 'light');
                return $pricesTtc[$level] ?? $pricesTtc['light'];
            });
            if ($order->discount_cents > 0) {
                $ttcC = max(0, $ttcC - $order->discount_cents);
            }
            $htC = (int) round($ttcC / 1.2);
            $tvaC = $ttcC - $htC;
        }

        $discountC = $order->discount_cents ?? 0;
        $baseTtcC  = $ttcC + $discountC;
        $baseHtC   = (int) round($baseTtcC / 1.2);
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">


        {{-- ── Contenu principal ── --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- === ÉTAT : EN COURS === --}}
            @if (in_array($order->status, ['PENDING', 'IN_PROGRESS']))
            {{-- Polling toutes les 10s pour détecter quand l'admin passe à DONE --}}
            <div wire:poll.20s="pollStatusChange" class="card-glass p-10 text-center">
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

            {{-- === ÉTAT : APERÇU PRÊT (DONE) — Sélection + Paiement === --}}
            @if ($order->status === 'DONE')
            <div wire:poll.15000ms="pollPaymentStatus">
            {{-- ── Email-gate : vérification que le client a cliqué le lien email ── --}}
            @if (! $order->preview_unlocked_at)
            <div class="card-glass p-10 text-center">
                {{-- Icône enveloppe animée --}}
                <div class="relative w-20 h-20 mx-auto mb-6">
                    <div class="absolute inset-0 border-2 border-[#C9A84C]/20 rounded-full"></div>
                    <div class="absolute inset-0 border-t-2 border-[#C9A84C] rounded-full animate-spin" style="animation-duration:3s"></div>
                    <svg class="absolute inset-0 m-auto w-8 h-8 text-[#C9A84C]/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="text-[#F5F0E8] text-lg font-semibold mb-2">Vos photos sont prêtes !</h3>
                <p class="text-[#7A6E5E] text-sm max-w-sm mx-auto leading-relaxed mb-8">
                    Vous allez recevoir (ou avez reçu) un email contenant un lien sécurisé pour visionner vos photos filigranées. Cliquez sur le bouton présent dans cet email pour déverrouiller l'aperçu.
                </p>
                <button wire:click="resendUnlockEmail"
                        wire:loading.attr="disabled"
                        class="btn-outline text-sm px-8 py-3 flex items-center gap-2 mx-auto">
                    <svg wire:loading wire:target="resendUnlockEmail" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <svg wire:loading.remove wire:target="resendUnlockEmail" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Je n'ai pas reçu l'email
                </button>
            </div>
            @else
            <div class="flex items-start gap-3 px-4 py-3 bg-[#C9A84C]/8 border border-[#C9A84C]/25 rounded-sm mb-4">
                <svg class="w-4 h-4 text-[#C9A84C] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <p class="text-[#C9A84C] text-sm font-medium">Validez votre sélection avant de payer</p>
                    <p class="text-[#7A6E5E] text-xs mt-0.5">Survolez une photo et cliquez ✕ pour la retirer — le prix se recalcule automatiquement. Vous ne payez que ce que vous gardez.</p>
                </div>
            </div>

            <div class="card-glass overflow-hidden">
                <div class="p-5 border-b border-[#C9A84C]/10 flex items-center justify-between">
                    <h3 class="text-[#F5F0E8] font-semibold">Vos photos restaurées</h3>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="text-emerald-400">{{ $activePhotos->count() }} sélectionnée{{ $activePhotos->count() > 1 ? 's' : '' }}</span>
                        @if ($rejectedPhotos->count() > 0)
                        <span class="text-red-400/80">{{ $rejectedPhotos->count() }} retirée{{ $rejectedPhotos->count() > 1 ? 's' : '' }}</span>
                        @endif
                    </div>
                </div>

                {{-- Grille sélection — hover Alpine + modals via $dispatch ── --}}
                <div x-data="{
                        pendingId: null,
                        confirmOpen: false,
                        deleteOpen: false
                     }"
                     @omr-reject.window="pendingId = $event.detail.id; confirmOpen = true"
                     @omr-delete.window="pendingId = $event.detail.id; deleteOpen = true">

                    {{-- ── Modal : Retirer (réversible) ── --}}
                    <div x-show="confirmOpen" 
                         class="fixed inset-0 z-[9999] flex items-center justify-center"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         x-cloak>
                        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="confirmOpen = false"></div>
                        <div class="relative z-10 w-full max-w-sm mx-4 bg-[#120F0A] border border-[#C9A84C]/20 rounded-sm shadow-2xl p-6 text-center">
                            <div class="w-12 h-12 border border-red-500/30 rounded-full flex items-center justify-center mx-auto mb-4 bg-red-900/20">
                                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </div>
                            <h3 class="text-[#F5F0E8] font-semibold mb-2">Retirer cette photo ?</h3>
                            <p class="text-[#7A6E5E] text-sm mb-5 leading-relaxed">Elle sera exclue de votre commande et ne sera pas facturée. Vous pouvez la réintégrer à tout moment avant de payer.</p>
                            <div class="flex gap-3 justify-center">
                                <button @click="confirmOpen = false"
                                        class="px-5 py-2 text-sm text-[#7A6E5E] border border-[#7A6E5E]/30 rounded-sm hover:border-[#C9A84C]/40 hover:text-[#F5F0E8] transition-all">
                                    Annuler
                                </button>
                                <button @click="confirmOpen = false; $wire.rejectPhoto(pendingId)"
                                        class="px-5 py-2 text-sm bg-red-700 text-white rounded-sm hover:bg-red-600 transition-colors font-semibold">
                                    Retirer
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- ── Modal : Suppression définitive ── --}}
                    <div x-show="deleteOpen" 
                         class="fixed inset-0 z-[9999] flex items-center justify-center"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         x-cloak>
                        <div class="absolute inset-0 bg-black/85 backdrop-blur-sm" @click="deleteOpen = false"></div>
                        <div class="relative z-10 w-full max-w-sm mx-4 bg-[#120F0A] border border-red-700/40 rounded-sm shadow-2xl p-6 text-center">
                            <div class="w-12 h-12 border-2 border-red-600/50 rounded-full flex items-center justify-center mx-auto mb-4 bg-red-900/30">
                                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </div>
                            <h3 class="text-red-300 font-bold mb-1">Suppression définitive</h3>
                            <p class="text-[#7A6E5E] text-xs mb-1 uppercase tracking-widest">Action irréversible</p>
                            <p class="text-[#7A6E5E] text-sm mb-5 leading-relaxed mt-3">Cette photo sera <strong class="text-red-400">supprimée définitivement</strong> et ne pourra pas être récupérée.</p>
                            <div class="flex gap-3 justify-center">
                                <button @click="deleteOpen = false"
                                        class="px-5 py-2 text-sm text-[#7A6E5E] border border-[#7A6E5E]/30 rounded-sm hover:border-[#C9A84C]/40 hover:text-[#F5F0E8] transition-all">
                                    Annuler
                                </button>
                                <button @click="deleteOpen = false; $wire.deletePhoto(pendingId)"
                                        class="px-5 py-2 text-sm bg-red-900 border border-red-600/50 text-red-200 rounded-sm hover:bg-red-800 transition-colors font-bold">
                                    🗑️ Supprimer définitivement
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- ── Grille photos ── --}}
                    <div class="p-5 grid grid-cols-2 md:grid-cols-3 gap-4">
                        @forelse ($retouched as $media)
                        @php
                            $isRejected  = $media->getCustomProperty('is_rejected', false);
                            $photoLevel  = $media->getCustomProperty('ai_level', $order->damage_level ?? 'light');
                            $photoPriceLabel = match($photoLevel) {
                                'medium' => '2 €',
                                'heavy'  => '3 €',
                                default  => '1 €',
                            };
                            $photoBadgeColor = match($photoLevel) {
                                'medium' => 'text-amber-400 bg-amber-950/60 border-amber-500/30',
                                'heavy'  => 'text-orange-400 bg-orange-950/60 border-orange-500/30',
                                default  => 'text-emerald-400 bg-emerald-950/60 border-emerald-500/30',
                            };
                        @endphp

                        {{-- Carte : hover géré par Alpine (pas de group-hover Tailwind) --}}
                        <div x-data="{ h: false }"
                             @mouseenter="h = true"
                             @mouseleave="h = false"
                             class="relative aspect-square bg-[#1A1510] rounded-sm overflow-hidden select-none cursor-default {{ $isRejected ? 'border-2 border-red-500/50' : 'border border-[#C9A84C]/15' }}"
                             style="{{ $isRejected ? 'opacity:0.6' : '' }}">

                            <img src="{{ route('client.orders.photo.show', [$order, $media]) }}"
                                 alt="Photo restaurée"
                                 class="w-full h-full object-cover pointer-events-none" draggable="false">

                            {{-- Le vrai watermark est incrusté côté serveur (SecurePhotoController) --}}
                            @if (! $isRejected)
                            <div class="absolute inset-0 pointer-events-none" style="box-shadow:inset 0 0 20px rgba(201,168,76,0.12);"></div>
                            @endif

                            {{-- ── Overlay photo retirée ── --}}
                            @if ($isRejected)
                            <div class="absolute inset-0 bg-red-950/80 flex flex-col items-center justify-center p-4">
                                <span class="text-red-200 text-[10px] font-bold uppercase tracking-widest mb-3">Photo retirée</span>
                                <div class="flex gap-2">
                                    {{-- Bouton Restaurer --}}
                                    <button wire:click="restorePhoto({{ $media->id }})"
                                            title="Réintégrer cette photo"
                                            class="w-10 h-10 flex items-center justify-center rounded-full bg-emerald-600 text-white shadow-lg hover:bg-emerald-500 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                    </button>
                                    {{-- Bouton Supprimer --}}
                                    <button @click="$dispatch('omr-delete', { id: {{ $media->id }} })"
                                            title="Supprimer définitivement"
                                            class="w-10 h-10 flex items-center justify-center rounded-full bg-red-900 border border-red-600/50 text-red-200 shadow-lg hover:bg-red-800 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                            @else
                            {{-- Bouton ✕ Retirer (haut-droit) --}}
                            <button x-show="h"
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-75"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    @click="$dispatch('omr-reject', { id: {{ $media->id }} })"
                                    title="Retirer cette photo"
                                    class="absolute top-2 right-2 z-20 w-8 h-8 flex items-center justify-center rounded-full text-sm font-bold bg-red-600 text-white shadow-lg hover:bg-red-500">
                                ✕
                            </button>
                            @endif

                            {{-- Badge prix individuel — en bas de la carte --}}
                            <div class="absolute bottom-2 left-2 z-10">
                                <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold rounded border
                                    {{ $isRejected ? 'text-[#7A6E5E]/60 bg-[#1A1510]/80 border-[#7A6E5E]/20 line-through' : $photoBadgeColor }}">
                                    {{ $photoPriceLabel }} TTC
                                </span>
                            </div>

                        </div>
                        @empty
                        <div class="col-span-3 py-8 text-center text-[#7A6E5E] text-sm">Aucune photo disponible.</div>
                        @endforelse
                    </div>
                </div>


                @if ($rejectedPhotos->count() > 0)
                <p class="px-5 pb-3 text-xs text-red-400/70">
                    {{ $rejectedPhotos->count() }} photo{{ $rejectedPhotos->count() > 1 ? 's retirées' : ' retirée' }} — vous ne serez pas facturé pour {{ $rejectedPhotos->count() > 1 ? 'ces photos' : 'cette photo' }}.
                </p>
                @endif

                {{-- CTA paiement --}}
                <div class="p-5 border-t border-[#C9A84C]/10 bg-[#C9A84C]/5 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div>
                        <p class="text-[#F5F0E8] text-sm font-medium">
                            {{ $activePhotos->count() > 0 ? 'Satisfait de votre sélection ?' : 'Toutes les photos ont été retirées.' }}
                        </p>
                        <p class="text-[#7A6E5E] text-xs">
                            @if ($activePhotos->count() > 0)
                                {{ $activePhotos->count() }} photo{{ $activePhotos->count() > 1 ? 's' : '' }} · téléchargement HD sans filigrane après paiement.
                            @else
                                Réintégrez au moins une photo pour procéder au paiement.
                            @endif
                        </p>
                    </div>
                    @if ($activePhotos->count() > 0)
                    <form action="{{ route('client.orders.checkout', $order) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn-gold whitespace-nowrap">
                            Payer — {{ number_format($payTtc / 100, 2, ',', ' ') }} € TTC
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            @endif {{-- fin @if (! $order->preview_unlocked_at) --}}
            </div>
            @endif {{-- fin @if ($order->status === 'DONE') --}}

            {{-- === ÉTAT : PAYÉ — En cours de traitement === --}}
            @if ($order->status === 'PAID')
            <div class="card-glass overflow-hidden" wire:poll.10s="pollDelivery">

                {{-- Bande dorée en haut --}}
                <div class="h-1 bg-gradient-to-r from-[#C9A84C] to-[#E8C97A]"></div>

                <div class="p-10 text-center">
                    {{-- Icône animée --}}
                    <div class="relative w-20 h-20 mx-auto mb-6">
                        <div class="absolute inset-0 border-2 border-[#C9A84C]/20 rounded-full"></div>
                        <div class="absolute inset-0 border-t-2 border-[#C9A84C] rounded-full animate-spin" style="animation-duration:2s"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <svg class="w-8 h-8 text-[#C9A84C]/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>

                    <h3 class="text-[#F5F0E8] text-xl font-semibold mb-2">En cours de traitement</h3>
                    <p class="text-[#7A6E5E] text-sm max-w-sm mx-auto leading-relaxed mb-2">
                        Votre paiement a bien été confirmé. Notre équipe procède à la vérification finale avant l'envoi de vos fichiers haute définition.
                    </p>
                    <p class="text-[#C9A84C]/70 text-xs max-w-xs mx-auto mb-8">
                        Vous recevrez un email dès que vos photos seront disponibles au téléchargement.
                        <span class="block mt-1 text-[#7A6E5E]/60">Surveillez votre boîte mail (et vos spams).</span>
                    </p>

                    {{-- Récapitulatif commande --}}
                    <div class="text-left max-w-xs mx-auto bg-[#0F0C08]/60 border border-[#C9A84C]/15 rounded-sm px-5 py-4 mb-8 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-[#7A6E5E]">Référence</span>
                            <span class="text-[#C9A84C] font-mono text-xs">{{ $order->reference }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#7A6E5E]">Photos</span>
                            <span class="text-[#F5F0E8]">{{ $order->photo_count }}</span>
                        </div>
                        @if ($order->paid_at)
                        <div class="flex justify-between">
                            <span class="text-[#7A6E5E]">Payé le</span>
                            <span class="text-emerald-400 text-xs">{{ $order->paid_at->format('d/m/Y à H:i') }}</span>
                        </div>
                        @endif
                    </div>

                    {{-- Facture PDF disponible immédiatement --}}
                    <a href="{{ route('client.orders.invoice', $order) }}" target="_blank"
                       class="inline-flex items-center gap-3 px-6 py-3 border border-[#C9A84C]/30 hover:border-[#C9A84C]/60 bg-[#C9A84C]/8 hover:bg-[#C9A84C]/15 rounded-sm transition-all group">
                        <div class="w-8 h-8 rounded-sm border border-[#C9A84C]/30 bg-[#C9A84C]/10 flex items-center justify-center shrink-0 group-hover:bg-[#C9A84C]/20 transition-colors">
                            <svg class="w-4 h-4 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div class="text-left">
                            <p class="text-[#F5F0E8] text-sm font-medium">Télécharger la facture PDF</p>
                            <p class="text-[#7A6E5E] text-xs mt-0.5">Facture officielle · {{ $order->reference }}</p>
                        </div>
                        <svg class="w-4 h-4 text-[#C9A84C]/50 group-hover:text-[#C9A84C] transition-colors ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                    </a>
                </div>
            </div>
            @endif

            {{-- === ÉTAT : LIVRÉ — Archive ZIP prête === --}}
            @if ($order->status === 'DELIVERED')
            <div class="space-y-6">
                {{-- Encadré principal : Téléchargement des photos --}}
                <div class="card-glass p-8 text-center border-[#C9A84C]/30 shadow-[0_8px_40px_rgba(201,168,76,0.15)] relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-4">
                         <div class="flex items-center gap-2 py-1.5 px-3 bg-[#C9A84C]/10 border border-[#C9A84C]/20 rounded-full">
                             <svg class="w-3.5 h-3.5 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                             <span class="text-[#C9A84C] text-[10px] font-bold uppercase tracking-widest">
                                Expire le {{ $order->zip_expires_at?->format('d/m/Y') ?? 'N/A' }}
                             </span>
                         </div>
                    </div>

                    <div class="w-16 h-16 border border-emerald-500/40 rounded-full flex items-center justify-center mx-auto mb-4 bg-emerald-500/5">
                        <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="text-[#F5F0E8] text-xl font-bold mb-2">Vos photos sont prêtes !</h3>
                    <p class="text-[#7A6E5E] text-sm mb-8 max-w-sm mx-auto leading-relaxed">
                        Votre archive sécurisée contient l'intégralité de vos photos restaurées en haute résolution (HD) et sans filigrane.
                    </p>
                    
                    @if ($order->zip_path)
                    <div class="mb-4">
                        <a href="{{ route('client.orders.download', $order) }}?t={{ time() }}"
                           target="_blank"
                           @click="setTimeout(() => $wire.refreshAfterDownload(), 1500)"
                           class="btn-gold text-lg px-12 py-5 inline-flex items-center gap-3 shadow-[0_10px_30px_rgba(201,168,76,0.25)] hover:scale-[1.02] transition-transform">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Télécharger l'archive ZIP
                        </a>
                    </div>
                    
                    @if($order->delivery && $order->delivery->download_count > 0)
                    <div class="flex items-center justify-center gap-1.5 mb-3 text-red-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span class="text-[11px] font-bold tracking-wider">PRODUIT TÉLÉCHARGÉ (REMBOURSEMENT NON APPLICABLE)</span>
                    </div>
                    @endif
                    
                    <p class="text-[#4A3E2E] text-[11px]">Format .ZIP · Haute Définition · Prêt pour impression</p>
                    @endif
                </div>

                {{-- Encadré séparé : Facture --}}
                <div class="card-glass p-6 border-[#C9A84C]/10 bg-[#1A1510]/30">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-6">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-sm border border-[#C9A84C]/20 bg-[#C9A84C]/10 flex items-center justify-center">
                                <svg class="w-6 h-6 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div class="text-left">
                                <h4 class="text-[#F5F0E8] font-semibold">Facture acquittée</h4>
                                <p class="text-[#7A6E5E] text-xs">Référence : {{ $order->reference }} · Reçu officiel</p>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                            <button @click="showInvoice = true"
                                    class="flex items-center justify-center gap-2 px-6 py-3 bg-[#C9A84C]/10 hover:bg-[#C9A84C]/20 border border-[#C9A84C]/30 text-[#C9A84C] text-sm font-bold rounded-sm transition-all group">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Consulter
                            </button>
                            <a href="{{ route('client.orders.invoice', $order) }}"
                               class="flex items-center justify-center gap-2 px-6 py-3 bg-transparent hover:bg-[#C9A84C]/5 border border-[#C9A84C]/20 text-[#C9A84C]/80 hover:text-[#C9A84C] text-sm font-medium rounded-sm transition-all group">
                                <svg class="w-4 h-4 group-hover:translate-y-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                PDF
                            </a>
                        </div>
                    </div>
                </div>
            </div>

                {{-- ✦ Laisser un avis ✦ --}}
                @php $existingTestimonial = \App\Models\Testimonial::where('order_id', $order->id)->first(); @endphp
                <div class="mt-6 pt-5 border-t border-[#C9A84C]/10">
                    @if ($existingTestimonial)
                    {{-- Avis déjà soumis --}}
                    <div class="flex items-center justify-center gap-2 text-xs">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-emerald-400 font-medium">Votre avis a été envoyé</span>
                        <span class="text-[#7A6E5E]">—
                            {{ $existingTestimonial->is_published ? 'visible sur le site !' : 'en attente de modération.' }}
                        </span>
                    </div>
                    @else
                    {{-- Formulaire de soumission --}}
                    <h4 class="text-[#F5F0E8] text-sm font-semibold mb-4 text-center">
                        ✦ Partagez votre expérience
                        <span class="text-[#7A6E5E] font-normal">— votre avis aide d'autres familles</span>
                    </h4>

                    {{-- Étoiles interactives (Alpine hover + Livewire model) --}}
                    <div class="flex justify-center gap-2 mb-4" x-data="{ hovered: 0 }">
                        @for ($s = 1; $s <= 5; $s++)
                        <button type="button"
                                x-on:mouseenter="hovered = {{ $s }}"
                                x-on:mouseleave="hovered = 0"
                                wire:click="$set('testimonialRating', {{ $s }})"
                                class="transition-transform hover:scale-110 focus:outline-none">
                            <svg class="w-7 h-7 transition-colors duration-150"
                                 :class="(hovered >= {{ $s }} || ($wire.testimonialRating >= {{ $s }} && hovered === 0)) ? 'text-[#C9A84C]' : 'text-[#3A3028]'"
                                 fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        </button>
                        @endfor
                    </div>

                    <textarea
                        wire:model="testimonialContent"
                        placeholder="Décrivez votre expérience (20 caractères minimum)..."
                        rows="3"
                        class="w-full bg-[#0F0C08] border border-[#3A3028] text-[#F5F0E8] rounded-sm px-4 py-3
                               text-sm placeholder-[#4A3E2E] focus:outline-none focus:border-[#C9A84C]/50
                               transition-colors resize-none"
                        maxlength="500"
                    ></textarea>
                    @error('testimonialContent')
                    <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                    <div class="flex justify-between items-center mt-1 mb-3">
                        <span class="text-[#4A3E2E] text-xs">Min. 20 caractères</span>
                        <span class="text-[#4A3E2E] text-xs" x-data x-text="$wire.testimonialContent.length + '/500'"></span>
                    </div>

                    <button wire:click="submitTestimonial"
                            wire:loading.attr="disabled"
                            class="w-full btn-gold py-3 text-sm flex items-center justify-center gap-2">
                        <svg wire:loading wire:target="submitTestimonial" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        ✦ Envoyer mon avis
                    </button>
                    @endif
                </div>
            @endif {{-- fin @if ($order->status === 'DELIVERED') --}}


            {{-- === ÉTAT : ANNULÉ === --}}
            @if ($order->status === 'CANCELLED')
            <div class="card-glass p-8 text-center border-red-500/20">
                <div class="w-16 h-16 border border-red-500/30 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <h3 class="text-[#F5F0E8] font-semibold mb-2">Commande annulée</h3>
                <p class="text-[#7A6E5E] text-sm mb-4">
                    Cette commande a été annulée. Aucun montant n'a été prélevé.
                </p>

                {{-- Raison d'annulation fournie par l'équipe --}}
                @if ($order->admin_notes)
                <div class="mb-6 mx-auto max-w-sm text-left px-4 py-3 bg-red-900/15 border border-red-500/25 rounded-sm">
                    <p class="text-red-400/70 text-xs uppercase tracking-widest mb-1 font-medium">Motif</p>
                    <p class="text-[#F5F0E8] text-sm">{{ $order->admin_notes }}</p>
                </div>
                @endif

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
                        <dd class="text-[#F5F0E8]">{{ $activePhotos->count() }} / {{ $order->photo_count }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-[#7A6E5E]">Type</dt>
                        <dd class="text-[#F5F0E8]">
                            @php
                                $breakdown = $order->getDamageBreakdown();
                                $isMixed = count($breakdown) > 1;

                                if ($isMixed) {
                                    $labels = [];
                                    if (isset($breakdown['heavy']))  $labels[] = $breakdown['heavy'] . ' Compl.';
                                    if (isset($breakdown['medium'])) $labels[] = $breakdown['medium'] . ' Avanc.';
                                    if (isset($breakdown['light']))  $labels[] = $breakdown['light'] . ' Std';
                                    $lvlLabel = 'Mixte (' . implode(', ', $labels) . ')';
                                } else {
                                    $lvlLabel = match($order->damage_level) {
                                        'light'  => 'Standard',
                                        'medium' => 'Avancée',
                                        'heavy'  => 'Complète',
                                        default  => ucfirst($order->damage_level ?? 'N/A'),
                                    };
                                }
                            @endphp
                            {{ $lvlLabel }}
                        </dd>
                    </div>
                    @php
                        // On utilise les variables dynamiques recalculées dans le composant (Volt)
                        // $payTtc est le montant final TTC après retrait des photos et remise.
                        // $payHt  est le montant final HT correspondant.
                        $ttcC = $payTtc;
                        $htC  = $payHt;
                        $tvaC = max(0, $ttcC - $htC);
                    @endphp
                    @if ($baseHtC > 0)
                    <div class="pt-2 border-t border-[#C9A84C]/10 space-y-1.5">
                        {{-- Estim. tarif --}}
                        <div class="flex justify-between text-xs">
                            <dt class="text-[#7A6E5E]">Tarif TTC estimé</dt>
                            <dd class="text-[#7A6E5E]">{{ number_format($baseTtcC / 100, 2, ',', ' ') }} €</dd>
                        </div>
                        {{-- Remise coupon (si applicable) --}}
                        @if ($discountC > 0)
                        <div class="flex justify-between text-xs">
                            <dt class="text-emerald-400/80">Remise ({{ $order->coupon_code }})</dt>
                            <dd class="text-emerald-400">−{{ number_format($discountC / 100, 2, ',', ' ') }} €</dd>
                        </div>
                        @endif
                        {{-- HT net --}}
                        <div class="flex justify-between text-xs">
                            <dt class="text-[#7A6E5E]">HT{{ $discountC > 0 ? ' net' : '' }}</dt>
                            <dd class="text-[#F5F0E8]">{{ number_format($htC / 100, 2, ',', ' ') }} €</dd>
                        </div>
                        {{-- TVA --}}
                        <div class="flex justify-between text-xs">
                            <dt class="text-[#7A6E5E]/70">TVA 20%</dt>
                            <dd class="text-[#7A6E5E]/70">{{ number_format($tvaC / 100, 2, ',', ' ') }} €</dd>
                        </div>
                        {{-- TTC --}}
                        <div class="flex justify-between font-semibold">
                            <dt class="text-[#C9A84C]">Total TTC</dt>
                            <dd class="text-[#C9A84C]">
                                {{ number_format($ttcC / 100, 2, ',', ' ') }} €
                            </dd>
                        </div>
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

            {{-- Aide & Support --}}
            <div class="space-y-3">
                @if ($order->status === 'DONE')
                {{-- Alerte spécifique si photos non visibles --}}
                <div class="card-glass p-4 bg-emerald-500/5 border-emerald-500/20">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <h4 class="text-[#F5F0E8] text-xs font-semibold mb-1">Un souci d'affichage ?</h4>
                            <p class="text-[#7A6E5E] text-[11px] leading-relaxed mb-2">
                                Si les photos ne s'affichent pas, essayez de rafraîchir la page (F5). Le traitement peut parfois prendre quelques secondes.
                            </p>
                            <button wire:click="resendUnlockEmail" 
                                    wire:loading.attr="disabled"
                                    class="text-[#C9A84C] text-[10px] uppercase tracking-wider font-bold hover:text-[#E8C97A] transition-colors flex items-center gap-1">
                                <svg wire:loading wire:target="resendUnlockEmail" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <span wire:loading.remove wire:target="resendUnlockEmail">✦ Renvoyer le lien par email</span>
                                <span wire:loading wire:target="resendUnlockEmail">Envoi...</span>
                            </button>
                        </div>
                    </div>
                </div>
                @endif

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

    {{-- ── Modal Facture ── --}}
    <div x-show="showInvoice" 
         class="fixed inset-0 z-[100] flex items-center justify-center p-4"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak>
        <div class="absolute inset-0 bg-[#0F0C08]/90 backdrop-blur-md" @click="showInvoice = false"></div>
        
        <div class="relative w-full max-w-3xl max-h-[90vh] overflow-y-auto scrollbar-hide bg-[#1A1510] border border-[#C9A84C]/20 rounded-sm shadow-2xl"
             x-transition:enter="transition ease-out duration-300 delay-100"
             x-transition:enter-start="opacity-0 translate-y-8 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100">
            
            <div class="sticky top-0 z-20 bg-[#1A1510]/80 backdrop-blur-md px-6 py-4 border-b border-[#C9A84C]/10 flex items-center justify-between">
                <h3 class="text-[#F5F0E8] font-semibold flex items-center gap-2">
                    <svg class="w-4 h-4 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Récapitulatif de paiement
                </h3>
                <button @click="showInvoice = false" class="text-[#7A6E5E] hover:text-[#F5F0E8] transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-6 sm:p-10">
                <x-invoice-preview :order="$order" />
            </div>
            
            <div class="px-6 py-4 border-t border-[#C9A84C]/10 flex justify-center bg-[#1A1510]/50">
                <a href="{{ route('client.orders.invoice', $order) }}" 
                   class="btn-gold text-xs px-6 py-2.5 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Télécharger le PDF officiel
                </a>
            </div>
        </div>
    </div>
</div>

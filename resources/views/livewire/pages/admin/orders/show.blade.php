<?php
/**
 * Admin — Gestion d'une commande
 * Route: GET /admin/orders/{order}
 *
 * Interface principale de travail admin :
 *   - Voir les photos originales du client
 *   - Prise en charge (PENDING → IN_PROGRESS)
 *   - Fixer le prix final (révision du verdict IA)
 *   - Uploader les photos restaurées
 *   - Marquer comme DONE (déclenche email client)
 *   - Annuler la commande
 */

use App\Models\Order;
use App\Services\AuditService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new
#[Layout('layouts.app')]
#[Title('Commande Admin')]
class extends Component
{
    use WithFileUploads;

    public Order $order;

    /** Photos restaurées uploadées par l'admin */
    public array $restoredPhotos = [];

    /** Prix final modifiable par l'admin (en euros, décimal) */
    public string $finalPrice = '';

    /** Notes internes admin */
    public string $adminNotes = '';

    /** Raison d'annulation */
    public string $cancelReason = '';

    /** Confirmé par le modal avant lancement de la restauration IA */
    public bool $aiConfirmOpen = false;

    public function mount(Order $order): void
    {
        $this->order      = $order->load(['user' => fn($u) => $u->withTrashed(), 'media', 'delivery', 'auditLogs', 'testimonial']);
        
        // Initialisation du prix en TTC pour l'affichage admin
        $this->finalPrice = number_format($this->order->getAmountTtcCents() / 100, 2, '.', '');
        $this->adminNotes = $this->order->admin_notes ?? '';
    }

    /** PENDING → IN_PROGRESS */
    public function takeCharge(AuditService $audit): void
    {
        $previous = $this->order->status;
        $this->order->startProcessing();
        $audit->orderStatusChanged($this->order, $previous, 'IN_PROGRESS');
        session()->flash('success', 'Commande prise en charge — statut : En cours');
        $this->order->refresh()->load(['user', 'media', 'delivery', 'auditLogs']);
    }

    /**
     * Lance la restauration IA automatique (dispatch du job en queue).
     * Appelé via Livewire après confirmation dans le modal custom.
     */
    public function launchAiRestoration(): void
    {
        if (! config('openai.api_key')) {
            session()->flash('error', 'Clé API OpenAI non configurée (OPENAI_API_KEY manquante dans .env).');
            $this->aiConfirmOpen = false;
            return;
        }

        if (! in_array($this->order->status, ['PENDING', 'IN_PROGRESS'])) {
            session()->flash('error', 'La restauration IA n\'est possible que sur une commande PENDING ou IN_PROGRESS.');
            $this->aiConfirmOpen = false;
            return;
        }

        $originalCount = $this->order->getMedia('originals')->count();
        if ($originalCount === 0) {
            session()->flash('error', 'Aucune photo originale trouvée sur cette commande.');
            $this->aiConfirmOpen = false;
            return;
        }

        \App\Jobs\AutoRestoreOrderPhotosJob::dispatch($this->order);

        $this->aiConfirmOpen = false;
        session()->flash('success', "🤖 Restauration IA lancée pour {$originalCount} photo(s) — résultats disponibles dans quelques minutes.");
        $this->order->refresh()->load(['user', 'media', 'delivery', 'auditLogs']);
    }

    /** Upload des photos restaurées (reste en IN_PROGRESS pour validation admin) */
    public function uploadRestoredPhotos(AuditService $audit): void
    {
        $this->validate([
            'restoredPhotos'   => ['required', 'array', 'min:1'],
            'restoredPhotos.*' => ['required', 'file', 'mimes:jpg,jpeg,png,tiff,tif,webp', 'max:51200'],
            'finalPrice'       => ['required', 'numeric', 'min:0'],
        ]);

        // Fixer le prix final TTC et les notes
        $this->order->update([
            'total_price_cents' => (int) round((float) $this->finalPrice * 100),
            'admin_notes'       => $this->adminNotes ?: null,
        ]);

        // Copier les fichiers temporaires Livewire AVANT qu'ils soient supprimés
        // (Livewire nettoie ses tmp après chaque requête Livewire)
        $tmpCopies = [];
        foreach ($this->restoredPhotos as $idx => $photo) {
            $src = $photo->getRealPath();
            if (! $src || ! file_exists($src)) {
                session()->flash('error', "Fichier introuvable : {$photo->getClientOriginalName()}. Réessayez.");
                return;
            }
            // Copier dans un répertoire stable sous storage/app
            $destDir = storage_path('app/tmp-admin-uploads');
            if (! is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            $ext      = $photo->getClientOriginalExtension();
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $photo->getClientOriginalName());
            $destPath = $destDir . '/' . uniqid("restore_{$idx}_") . '.' . $ext;
            copy($src, $destPath);
            $tmpCopies[] = ['path' => $destPath, 'name' => 'restored_' . $safeName];
        }

        // Construire un mapping index → ai_level depuis les photos originales
        $originalsLevels = $this->order->getMedia('originals')
            ->values()
            ->mapWithKeys(fn($m, $i) => [$i => $m->getCustomProperty('ai_level', $this->order->damage_level ?? 'light')])
            ->toArray();

        // Passer les copies stables à Spatie MediaLibrary
        $uploaded = 0;
        foreach ($tmpCopies as $idx => $tmp) {
            // Tenter de retrouver l'index depuis le nom du fichier (ex: IMG-02)
            $aiLevel = $this->order->damage_level ?? 'light';
            
            if (preg_match('/IMG-(\d+)/i', $tmp['name'], $matches)) {
                $imgIndex = (int)$matches[1] - 1; // Retour à 0-based
                if (isset($originalsLevels[$imgIndex])) {
                    $aiLevel = $originalsLevels[$imgIndex];
                }
            } else {
                // Fallback si l'admin n'a pas utilisé le bon nommage
                $aiLevel = $originalsLevels[$idx] ?? $this->order->damage_level ?? 'light';
            }

            try {
                $this->order
                    ->addMedia($tmp['path'])
                    ->usingFileName($tmp['name'])
                    ->withCustomProperties([
                        'uploaded_by_admin' => true,
                        'ai_level'          => $aiLevel,
                    ])
                    ->preservingOriginal()
                    ->toMediaCollection('retouched');

                unlink($tmp['path']);
                $uploaded++;
            } catch (\Throwable $e) {
                @unlink($tmp['path']);

                \Illuminate\Support\Facades\Log::error("Admin upload failed: {$e->getMessage()}", [
                    'order_id' => $this->order->id,
                    'file'     => $tmp['name'],
                    'trace'    => $e->getTraceAsString(),
                ]);
                session()->flash('error', "Erreur upload {$tmp['name']} : {$e->getMessage()}");
                return;
            }
        }

        $this->restoredPhotos = [];
        session()->flash('success', "{$uploaded} photo(s) restaurée(s) uploadée(s). La commande est prête pour notification.");
        $this->order->refresh()->load(['user', 'media', 'delivery', 'auditLogs']);
    }

    /** Mise à jour du prix et des notes sans changer le statut */
    public function saveNotes(): void
    {
        $this->validate([
            'finalPrice' => ['required', 'numeric', 'min:0'],
            'adminNotes' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->order->update([
            'total_price_cents' => (int) round((float) $this->finalPrice * 100),
            'admin_notes'       => $this->adminNotes ?: null,
        ]);
        session()->flash('success', 'Notes et prix TTC sauvegardés.');
    }

    /** Annuler la commande */
    public function cancelOrder(AuditService $audit): void
    {
        $this->validate(['cancelReason' => ['required', 'string', 'min:10', 'max:500']]);
        $previous = $this->order->status;
        $this->order->cancel($this->cancelReason);
        $audit->orderStatusChanged($this->order, $previous, 'CANCELLED');
        session()->flash('success', 'Commande annulée.');
        $this->order->refresh()->load(['user', 'media', 'delivery', 'auditLogs']);
    }

    // ── Phase d'Urgence : CSAM / NSFW ────────────────────────────────────────

    public function ignoreReport(): void
    {
        $this->order->forceFill(['status' => 'PENDING'])->save();
        session()->flash('success', 'Signalement ignoré. La commande est de nouveau en attente.');
        $this->order->refresh()->load(['user', 'media', 'delivery', 'auditLogs']);
    }

    public function banAndDestroy(): void
    {
        // Détruire tous les médias physiquement
        $this->order->clearMediaCollection('originals');
        $this->order->clearMediaCollection('retouched');
        $this->order->clearMediaCollection('watermarked');
        
        // Annuler la commande
        $this->order->cancel('Bannissement pour non-respect des CGU / Contenu sensible.');
        
        // Bannir l'utilisateur (Soft delete) si non déjà supprimé
        if ($this->order->user && !$this->order->user->trashed()) {
            $this->order->user->delete();
        }

        session()->flash('success', 'Médias détruits, commande annulée et utilisateur banni.');
        $this->redirect(route('admin.dashboard'), navigate: true);
    }

    public function generatePharosReport(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $email = $this->order->user ? $this->order->user->email : $this->order->billing_email;
        $ip = $this->order->client_ip ?? 'Non enregistrée';
        $content = "RAPPORT SIGNALEMENT PHAROS\n"
                 . "============================\n"
                 . "Référence commande : {$this->order->reference}\n"
                 . "Date : {$this->order->created_at->format('d/m/Y H:i:s')}\n"
                 . "Email compte : {$email}\n"
                 . "Adresse IP : {$ip}\n"
                 . "Notes admin / Détection : {$this->order->admin_notes}\n";
        
        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, 'rapport-pharos-' . $this->order->reference . '.txt');
    }

    // ── Phase D : Rejet de photos restaurées ────────────────────────────────

    /**
     * Marque une photo restaurée comme rejetée, puis recalcule le prix HT de la commande.
     * Utilise les custom_properties de Spatie MediaLibrary (pas de migration nécessaire).
     */
    public function rejectPhoto(int $mediaId): void
    {
        $media = $this->order->getMedia('retouched')->firstWhere('id', $mediaId);
        abort_if(! $media, 404, 'Photo introuvable.');

        $media->setCustomProperty('is_rejected', true)
              ->setCustomProperty('rejected_at', now()->toISOString())
              ->save();

        $this->recalcPriceFromActivePhotos();

        session()->flash('success', 'Photo rejetée — prix recalculé, exclue du livrable.');
        $this->order->refresh()->load(['user', 'media', 'delivery', 'auditLogs']);
    }

    /**
     * Restaure une photo rejetée (annule le rejet), puis recalcule le prix HT.
     */
    public function restorePhoto(int $mediaId): void
    {
        $media = $this->order->getMedia('retouched')->firstWhere('id', $mediaId);
        abort_if(! $media, 404, 'Photo introuvable.');

        $media->forgetCustomProperty('is_rejected')
              ->forgetCustomProperty('rejected_at')
              ->save();

        $this->recalcPriceFromActivePhotos();

        session()->flash('success', 'Photo réintégrée dans le livrable — prix mis à jour.');
        $this->order->refresh()->load(['user', 'media', 'delivery', 'auditLogs']);
    }

    /**
     * Recalcule total_price_cents d'après le nombre de photos actives (non rejetées).
     *
     * Logique :
     *   1. Nouveau prix HT brut = photos_actives × prix_par_photo (selon damage_level)
     *   2. Si un coupon est attaché à la commande → recalculer la remise sur la nouvelle base
     *   3. total_price_cents = brut − remise  (net HT, jamais négatif)
     *   4. discount_cents mis à jour pour refléter la nouvelle remise
     *
     * Prix HT par photo :
     *   light  →  83 cts (1,00 € TTC)
     *   medium → 167 cts (2,00 € TTC)
     *   heavy  → 417 cts (5,00 € TTC)
     */
    private function recalcPriceFromActivePhotos(): void
    {
        $pricesTtc = \App\Services\PhotoDamageAnalyzer::PRICES_TTC;
        
        // 1. Compter les photos actives (non rejetées)
        $activePhotos = $this->order->getMedia('retouched')
            ->filter(fn($m) => ! $m->getCustomProperty('is_rejected', false));

        // Somme TTC par photo selon son niveau individuel
        $newBaseTtc = $activePhotos->sum(function ($media) use ($pricesTtc) {
            $level = $media->getCustomProperty('ai_level', $this->order->damage_level ?? 'light');
            return $pricesTtc[$level] ?? $pricesTtc['light'];
        });

        // 2. Réappliquer le coupon sur la nouvelle base TTC
        $newDiscount = 0;
        if ($this->order->coupon_code) {
            $coupon = \App\Models\Coupon::where('code', $this->order->coupon_code)->first();
            if ($coupon) {
                // Utilisation de la nouvelle logique TTC
                $newDiscount = $coupon->discountTtcCents($newBaseTtc);
            }
        }

        $newTotalTtc = max(0, $newBaseTtc - $newDiscount);

        // 3. Sauvegarder : total_price_cents est désormais le TTC net
        $this->order->update([
            'total_price_cents' => $newTotalTtc,
            'discount_cents'    => $newDiscount,
        ]);

        \Illuminate\Support\Facades\Log::info(
            "Prix TTC recalculé {$this->order->reference}: {$activePhotos->count()} photo(s) = {$newTotalTtc} cts TTC net."
        );

        // 4. Synchroniser finalPrice dans l'interface admin
        $this->finalPrice = number_format($newTotalTtc / 100, 2, '.', '');
    }
    /**
     * Supprime définitivement une photo retouchée de la collection 'retouched'.
     * Réservé aux statuts PENDING / IN_PROGRESS (avant que le client voie les photos).
     * En statut DONE+, le client gère lui-même via son espace.
     */
    public function deleteRetouchedPhoto(int $mediaId): void
    {
        $media = $this->order->getMedia('retouched')->firstWhere('id', $mediaId);
        abort_if(! $media, 404, 'Photo introuvable.');

        \Illuminate\Support\Facades\Log::info(
            "Admin delete retouched media#{$mediaId} on order {$this->order->reference}"
        );

        $media->delete();

        session()->flash('success', 'Photo supprimée définitivement.');
        $this->order->refresh()->load(['user', 'media', 'delivery', 'auditLogs']);
    }

    /**
     * Finalise la commande (si IN_PROGRESS → DONE) et envoie l'email de validation.
     * Disponible pour les commandes IN_PROGRESS (si photos présentes) ou DONE.
     * Rate limité : 1 envoi par 5 minutes.
     */
    public function notifyClient(AuditService $audit): void
    {
        // On peut notifier si DONE ou si IN_PROGRESS avec des photos
        if (!in_array($this->order->status, ['IN_PROGRESS', 'DONE'])) {
            abort(403, 'Notification non disponible pour ce statut.');
        }

        // Si on est encore en IN_PROGRESS, on valide d'abord la commande
        if ($this->order->status === 'IN_PROGRESS') {
             if ($this->order->getMedia('retouched')->isEmpty()) {
                 session()->flash('error', "Uploadez des photos restaurées avant de notifier le client.");
                 return;
             }
             
             $previous = $this->order->status;
             $this->order->markAsDone();
             $audit->orderStatusChanged($this->order, $previous, 'DONE');
        }

        $sessionKey = "admin_resend_{$this->order->id}";
        if (session()->has($sessionKey) && now()->diffInSeconds(session($sessionKey)) < 300) {
            $remaining = 300 - now()->diffInSeconds(session($sessionKey));
            session()->flash('error', "Patientez encore {$remaining} secondes avant de renvoyer.");
            return;
        }

        if (!$this->order->user || $this->order->user->anonymized_at) {
            session()->flash('error', "Impossible d'envoyer un email : l'utilisateur a supprimé son compte.");
            return;
        }

        \Illuminate\Support\Facades\Mail::to($this->order->user->email)
            ->queue(new \App\Mail\OrderReadyForPayment($this->order));

        session()->put($sessionKey, now());
        session()->flash('success', "📧 Email de notification envoyé à {$this->order->user->email}. Statut : Aperçu prêt.");
        $this->order->refresh()->load(['user', 'media', 'delivery', 'auditLogs', 'testimonial']);
    }
    /**
     * Envoie l'email de livraison (ZIP + facture PDF) au client.
     * Disponible uniquement pour les commandes PAID ou DELIVERED.
     * Rate limité : 1 envoi par 5 minutes.
     */
    public function sendDeliveryEmail(AuditService $audit): void
    {
        abort_if(! in_array($this->order->status, ['PAID', 'DELIVERED']), 403, 'Livraison disponible uniquement après paiement.');

        $sessionKey = "admin_delivery_{$this->order->id}";
        if (session()->has($sessionKey) && now()->diffInSeconds(session($sessionKey)) < 300) {
            $remaining = 300 - now()->diffInSeconds(session($sessionKey));
            session()->flash('error', "Patientez encore {$remaining} secondes avant de renvoyer.");
            return;
        }

        if (!$this->order->user || $this->order->user->anonymized_at) {
            session()->flash('error', "Impossible d'envoyer un email : l'utilisateur a supprimé son compte.");
            return;
        }

        $previousStatus = $this->order->status;
        
        if ($previousStatus !== 'DELIVERED') {
            $this->order->markAsDelivered();
            $audit->orderStatusChanged($this->order, $previousStatus, 'DELIVERED');
        }

        \Illuminate\Support\Facades\Mail::to($this->order->user->email)
            ->queue(new \App\Mail\OrderDeliveryReady($this->order));

        session()->put($sessionKey, now());
        session()->flash('success', "📧 Email de livraison renvoyé (lien de téléchargement + facture) à {$this->order->user->email}.");
        $this->order->refresh()->load(['user', 'media', 'delivery', 'auditLogs', 'testimonial']);
    }

    /**
     * Polling 10s sur la page admin — détecte quand le paiement Stripe arrive
     * pendant que l'admin a la commande ouverte (statut DONE → PAID).
     */
    public function pollPaymentStatus(): void
    {
        if ($this->order->status !== 'DONE') {
            return;
        }

        $fresh = $this->order->fresh();

        if ($fresh->status === 'PAID') {
            $this->order = $fresh->load(['user', 'media', 'delivery', 'auditLogs', 'testimonial']);
            $this->dispatch('payment-received');
        }
    }

    /**
     * Effectue le remboursement Stripe intégral et annule la commande (supprime le ZIP).
     */
    public function refundOrder(AuditService $audit): void
    {
        abort_if(! in_array($this->order->status, ['PAID', 'DELIVERED']), 403, 'Seules les commandes payées peuvent être remboursées.');
        abort_if(! $this->order->payment_intent_id, 400, 'Aucun Payment Intent Stripe associé à cette commande.');

        try {
            \Stripe\Stripe::setApiKey(config('cashier.secret'));
            \Stripe\Refund::create([
                'payment_intent' => $this->order->payment_intent_id,
            ]);

            $previousStatus = $this->order->status;
            
            // Invalider le livrable en supprimant toutes les photos retouchées
            $this->order->clearMediaCollection('retouched');
            
            // Supprimer physiquement le ZIP s'il existe
            if ($this->order->delivery && $this->order->delivery->zip_path) {
                \Illuminate\Support\Facades\Storage::delete($this->order->delivery->zip_path);
            }

            $this->order->update([
                'payment_status' => 'refunded',
                'status' => 'CANCELLED',
                'admin_notes' => ($this->order->admin_notes ? $this->order->admin_notes . "\n" : '') . "Remboursement Stripe effectué le " . now()->format('d/m/Y H:i') . " - Livrable détruit.",
            ]);

            $audit->orderStatusChanged($this->order, $previousStatus, 'CANCELLED');
            
            session()->flash('success', 'Remboursement intégral effectué. Les fichiers ont été détruits et la commande est annulée.');
            $this->order->refresh()->load(['user', 'media', 'delivery', 'auditLogs', 'testimonial']);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erreur remboursement Stripe Order {$this->order->reference}: " . $e->getMessage());
            session()->flash('error', "Échec du remboursement : " . $e->getMessage());
        }
    }
}; ?>

<div x-data="{ finalHt: {{ (float)($finalPrice ?? 0) }}, showPaymentToast: false }"
     @payment-received.window="showPaymentToast = true"
     @if($order->status === 'DONE') wire:poll.1s="pollPaymentStatus" @endif>

    {{-- Toast Paiement reçu (déclenché par pollPaymentStatus) --}}
    <template x-teleport="body">
        <div x-show="showPaymentToast" x-cloak
             x-transition:enter="transition ease-out duration-500"
             x-transition:enter-start="opacity-0 translate-y-4 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             class="fixed bottom-6 right-6 z-[9999] max-w-sm w-full"
             style="filter: drop-shadow(0 20px 40px rgba(0,0,0,0.6));">
            <div class="relative bg-[#1A1510] border border-emerald-500/50 rounded-sm overflow-hidden">
                <div class="h-0.5 bg-gradient-to-r from-emerald-500 to-[#C9A84C]"></div>
                <div class="p-5 flex items-start gap-4">
                    <div class="shrink-0 w-10 h-10 rounded-full bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-[#F5F0E8] text-sm font-semibold">Paiement reçu !</p>
                        <p class="text-[#7A6E5E] text-xs mt-1">Le client vient de régler la commande. Vous pouvez maintenant envoyer le ZIP.</p>
                    </div>
                    <button @click="showPaymentToast = false" class="shrink-0 text-[#7A6E5E] hover:text-[#F5F0E8]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- Messages flash (Livewire re-render uniquement — le layout ne se met pas à jour en AJAX) --}}
    @if (session('success'))
    <div class="mb-6 flex items-center gap-3 bg-emerald-900/30 border border-emerald-500/30 text-emerald-400 text-sm px-4 py-3 rounded-sm">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if (session('error'))
    <div class="mb-6 flex items-center gap-3 bg-red-900/30 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-sm">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('error') }}
    </div>
    @endif

    <div class="flex items-center gap-4 mb-8">
        <a href="{{ route('admin.orders.index') }}" wire:navigate class="text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div class="flex-1">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-[#F5F0E8]">Commande</h1>
                <span class="font-mono text-[#C9A84C]">{{ $order->reference }}</span>
                @php
                    $badges = ['PENDING'=>'bg-yellow-900/40 text-yellow-400 border-yellow-500/30','IN_PROGRESS'=>'bg-blue-900/40 text-blue-400 border-blue-500/30','DONE'=>'bg-[#C9A84C]/15 text-[#C9A84C] border-[#C9A84C]/30','PAID'=>'bg-emerald-900/40 text-emerald-400 border-emerald-500/30','DELIVERED'=>'bg-emerald-900/40 text-emerald-400 border-emerald-500/30','CANCELLED'=>'bg-red-900/30 text-red-400 border-red-500/30','FLAGGED'=>'bg-red-900 text-white border-red-400 animate-pulse'];
                    $labels = ['PENDING'=>'En attente','IN_PROGRESS'=>'En cours','DONE'=>'Aperçu prêt','PAID'=>'Payé ✓','DELIVERED'=>'Livrée ✓','CANCELLED'=>'Annulé','FLAGGED'=>'🚨 SIGNALÉ (NSFW/CSAM)'];
                @endphp
                <span class="px-2.5 py-1 text-xs border rounded-full {{ $badges[$order->status] ?? '' }}">
                    {{ $labels[$order->status] ?? $order->status }}
                </span>
            </div>
            <p class="text-[#7A6E5E] text-sm mt-1">
                {{ $order->user?->name ?? 'Utilisateur supprimé' }} · {{ $order->user?->email ?? '—' }} · {{ $order->created_at->format('d/m/Y H:i') }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- ── Colonne principale ── --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Photos originales --}}
            <div class="card-glass overflow-hidden">
                <div class="px-5 py-4 border-b border-[#C9A84C]/10 flex items-center justify-between">
                    <h2 class="text-[#F5F0E8] font-semibold">Photos originales</h2>
                    <span class="text-[#7A6E5E] text-xs">{{ $order->photo_count }} photo{{ $order->photo_count > 1 ? 's' : '' }}</span>
                </div>
                <div class="p-5">
                    @if ($order->getMedia('originals')->isEmpty())
                    <p class="text-[#7A6E5E] text-sm text-center py-6">Aucune photo originale attachée</p>
                    @else
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        @foreach ($order->getMedia('originals') as $media)
                        <div x-data="{ revealed: false }" class="group relative aspect-square bg-[#1A1510] rounded-sm overflow-hidden border {{ $media->getCustomProperty('is_nsfw') ? 'border-red-500' : 'border-[#C9A84C]/10' }}">
                            <img src="{{ route('admin.orders.photo.show', [$order, $media]) }}" alt="{{ $media->file_name }}" class="w-full h-full object-cover transition-all duration-300" :class="revealed ? '' : '{{ $media->getCustomProperty('is_nsfw') ? 'blur-2xl' : '' }}'">
                            
                            @if($media->getCustomProperty('is_nsfw'))
                            <div x-show="!revealed" class="absolute inset-0 flex flex-col items-center justify-center bg-black/50 text-center p-2 z-10">
                                <svg class="w-8 h-8 text-red-500 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <span class="text-xs text-white font-bold">NSFW / CSAM</span>
                                <button @click="revealed = true" type="button" class="mt-2 text-[10px] bg-red-600/80 hover:bg-red-600 px-2 py-1 rounded text-white">Révéler</button>
                            </div>
                            @endif

                            <a x-show="revealed || !{{ $media->getCustomProperty('is_nsfw') ? 'true' : 'false' }}" href="{{ route('admin.orders.photo.show', [$order, $media]) }}" target="_blank"
                               class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center z-20">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </a>
                            {{-- Propriétés IA si disponibles --}}
                            @php $aiLevel = $media->getCustomProperty('ai_level', $order->damage_level ?? 'light'); @endphp
                            @if ($aiLevel)
                            <div class="absolute top-1 left-1">
                                <span class="text-[9px] px-1.5 py-0.5 rounded-full font-bold 
                                    {{ $aiLevel === 'heavy' ? 'bg-orange-500 text-black' : ($aiLevel === 'medium' ? 'bg-[#C9A84C] text-black' : 'bg-emerald-500 text-black') }}">
                                    {{ $aiLevel === 'heavy' ? '3 €' : ($aiLevel === 'medium' ? '2 €' : '1 €') }}
                                </span>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @endif

                    {{-- Instructions client --}}
                    @if ($order->instructions)
                    <div class="mt-4 p-3 bg-[#1A1510] rounded-sm border border-[#C9A84C]/10">
                        <p class="text-[#7A6E5E] text-xs font-medium mb-1 uppercase tracking-widest">Instructions client</p>
                        <p class="text-[#F5F0E8] text-sm">{{ $order->instructions }}</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- === ACTION : PRISE EN CHARGE === --}}
            @if ($order->status === 'PENDING')
            <div class="card-glass p-6 border-yellow-500/20">
                <h2 class="text-[#F5F0E8] font-semibold mb-3">Prendre en charge</h2>
                <p class="text-[#7A6E5E] text-sm mb-5">Cliquez pour démarrer la restauration. Le statut passera à "En cours".</p>
                <button wire:click="takeCharge" wire:loading.attr="disabled"
                        class="btn-gold">
                    <span wire:loading.remove wire:target="takeCharge">
                        <svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Prendre en charge
                    </span>
                    <span wire:loading wire:target="takeCharge" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        En cours…
                    </span>
                </button>
            </div>
            @endif

            {{-- === RESTAURATION IA AUTOMATIQUE (Phase 8) === --}}
            {{-- Disponible uniquement EN COURS (après prise en charge) --}}
            @if ($order->status === 'IN_PROGRESS')
            <div class="card-glass overflow-hidden border border-purple-500/20">
                <div class="px-5 py-4 border-b border-purple-500/15 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-purple-500/10 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2M9 9h.01M15 9h.01"/></svg>
                    </div>
                    <div>
                        <h2 class="text-[#F5F0E8] font-semibold text-sm">Restauration IA automatique</h2>
                        <p class="text-purple-400/70 text-xs">GPT-4o Vision + DALL-E 3 HD + Upscale 8K</p>
                    </div>
                    <span class="ml-auto px-2 py-0.5 bg-purple-500/10 text-purple-400 text-xs border border-purple-500/20 rounded-full">
                        Phase 8
                    </span>
                </div>

                <div class="p-5">
                    {{-- Description du pipeline --}}
                    <div class="grid grid-cols-3 gap-3 mb-5">
                        <div class="text-center p-3 bg-[#1A1510] rounded-sm border border-purple-500/10">
                            <div class="text-purple-400 text-lg mb-1">👁</div>
                            <p class="text-[#F5F0E8] text-xs font-medium">Analyse</p>
                            <p class="text-[#7A6E5E] text-[10px] mt-0.5">GPT-4o Vision</p>
                        </div>
                        <div class="text-center p-3 bg-[#1A1510] rounded-sm border border-purple-500/10">
                            <div class="text-purple-400 text-lg mb-1">✨</div>
                            <p class="text-[#F5F0E8] text-xs font-medium">Restauration</p>
                            <p class="text-[#7A6E5E] text-[10px] mt-0.5">DALL-E 3 HD</p>
                        </div>
                        <div class="text-center p-3 bg-[#1A1510] rounded-sm border border-purple-500/10">
                            <div class="text-purple-400 text-lg mb-1">🔍</div>
                            <p class="text-[#F5F0E8] text-xs font-medium">Upscale</p>
                            <p class="text-[#7A6E5E] text-[10px] mt-0.5">8K · 7680×4320</p>
                        </div>
                    </div>

                    {{-- Détection de colorisation depuis la description --}}
                    @php
                        $descText        = strtolower(($order->description ?? '') . ' ' . ($order->instructions ?? ''));
                        $wantsColor      = str_contains($descText, 'coloris') || str_contains($descText, 'en couleur') || str_contains($descText, 'ajouter les couleurs');
                        $wantsBW         = str_contains($descText, 'noir et blanc') || str_contains($descText, 'n&b') || str_contains($descText, 'monochrome');
                        $aiOriginalCount = $order->getMedia('originals')->count();
                    @endphp
                    @if ($wantsColor)
                    <div class="flex items-center gap-2 mb-4 px-3 py-2 bg-amber-900/20 border border-amber-500/20 rounded-sm">
                        <span class="text-amber-400">🎨</span>
                        <p class="text-amber-400 text-xs">Colorisation détectée — le modèle coloriera les photos N&B en couleur réaliste.</p>
                    </div>
                    @elseif ($wantsBW)
                    <div class="flex items-center gap-2 mb-4 px-3 py-2 bg-slate-800/40 border border-slate-500/20 rounded-sm">
                        <span class="text-slate-400">⬛</span>
                        <p class="text-slate-400 text-xs">Conversion N&B détectée — le modèle convertira les photos en noir et blanc argentique.</p>
                    </div>
                    @endif

                    {{-- Info coût estimé --}}
                    <div class="flex items-start gap-2 mb-5 px-3 py-2 bg-[#1A1510] border border-[#C9A84C]/10 rounded-sm">
                        <svg class="w-4 h-4 text-[#7A6E5E] mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <p class="text-[#7A6E5E] text-xs">
                                Coût estimé : <span class="text-[#C9A84C] font-medium">~{{ number_format($aiOriginalCount * 0.06, 2) }}$</span>
                                ({{ $aiOriginalCount }} photo{{ $aiOriginalCount > 1 ? 's' : '' }} × ~$0.06)
                            </p>
                            <p class="text-[#7A6E5E] text-[10px] mt-0.5">Les résultats apparaîtront automatiquement dans "Photos retouchées" une fois le traitement terminé.</p>
                        </div>
                    </div>

                    {{-- Bouton de lancement → ouvre modal custom --}}
                    <button type="button"
                            wire:click="$set('aiConfirmOpen', true)"
                            class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-purple-600/20 hover:bg-purple-600/30 border border-purple-500/30 hover:border-purple-500/50 text-purple-300 hover:text-purple-200 rounded-sm transition-all text-sm font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        🤖 Lancer la restauration IA automatique
                    </button>

                    {{-- Modal de confirmation custom --}}
                    @if ($aiConfirmOpen)
                    <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
                         style="background: rgba(0,0,0,0.75); backdrop-filter: blur(4px);">
                        <div class="w-full max-w-md bg-[#1C1812] border border-purple-500/30 rounded-sm shadow-2xl p-6"
                             x-data x-trap="true">
                            <div class="flex items-start gap-4 mb-5">
                                <div class="w-10 h-10 rounded-full bg-purple-500/10 flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </div>
                                <div>
                                    <h3 class="text-[#F5F0E8] font-semibold mb-1">Confirmer la restauration IA</h3>
                                    <p class="text-[#7A6E5E] text-sm">
                                        Lancer la restauration automatique pour
                                        <strong class="text-purple-300">{{ $aiOriginalCount }} photo{{ $aiOriginalCount > 1 ? 's' : '' }}</strong> ?<br>
                                        Cette opération consomme ~<strong class="text-[#C9A84C]">{{ number_format($aiOriginalCount * 0.06, 2) }}$</strong> de crédits OpenAI.
                                    </p>
                                </div>
                            </div>
                            <div class="p-3 bg-purple-500/5 border border-purple-500/10 rounded-sm mb-5">
                                <p class="text-[#7A6E5E] text-xs">
                                    Pipeline : 👁️ GPT-4o Vision → ✨ DALL-E 3 HD → 🔍 Upscale 8K<br>
                                    Les photos restaurées apparaissent automatiquement une fois le job terminé.
                                </p>
                            </div>
                            <div class="flex gap-3 justify-end">
                                <button type="button"
                                        wire:click="$set('aiConfirmOpen', false)"
                                        class="px-4 py-2 text-sm text-[#7A6E5E] hover:text-[#F5F0E8] border border-[#2A2520] hover:border-[#3A3028] rounded-sm transition-all">
                                    Annuler
                                </button>
                                <button type="button"
                                        wire:click="launchAiRestoration"
                                        wire:loading.attr="disabled"
                                        class="px-5 py-2 text-sm bg-purple-600/30 hover:bg-purple-600/50 border border-purple-500/40 text-purple-300 hover:text-purple-100 rounded-sm transition-all flex items-center gap-2">
                                    <span wire:loading.remove wire:target="launchAiRestoration">
                                        🤖 Oui, lancer la restauration
                                    </span>
                                    <span wire:loading wire:target="launchAiRestoration" class="flex items-center gap-2">
                                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        Lancement...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- === ACTION : UPLOAD (Statut IN_PROGRESS) === --}}
            @if ($order->status === 'IN_PROGRESS')
            <form wire:submit="uploadRestoredPhotos" class="card-glass p-6 border-blue-500/20">
                <h2 class="text-[#F5F0E8] font-semibold mb-1">Uploader les photos restaurées</h2>
                <p class="text-[#7A6E5E] text-sm mb-5">Une fois uploadées, vous pourrez envoyer l'email de validation au client.</p>

                {{-- Zone upload --}}
                <label for="restored-input"
                       class="block border-2 border-dashed border-blue-500/20 hover:border-blue-500/40 rounded-sm p-8 text-center cursor-pointer transition-all hover:bg-blue-500/3 mb-4">
                    <svg class="w-10 h-10 text-blue-400/30 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    <p class="text-[#F5F0E8] text-sm font-medium">Photos restaurées (JPEG, PNG, TIFF, WebP)</p>
                    <p class="text-[#7A6E5E] text-xs mt-1">50 Mo max par fichier</p>
                    <input id="restored-input" type="file" wire:model="restoredPhotos" multiple accept=".jpg,.jpeg,.png,.tiff,.tif,.webp" class="hidden">
                </label>

                @error('restoredPhotos.*') <p class="text-red-400 text-xs mb-3">{{ $message }}</p> @enderror

                {{-- Aperçu --}}
                @if (count($restoredPhotos) > 0)
                <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 mb-4">
                    @foreach ($restoredPhotos as $photo)
                    <div class="aspect-square bg-[#1A1510] rounded-sm overflow-hidden border border-blue-500/20">
                        <img src="{{ $photo->temporaryUrl() }}" class="w-full h-full object-cover">
                    </div>
                    @endforeach
                </div>
                <p class="text-blue-400 text-xs mb-4">{{ count($restoredPhotos) }} photo{{ count($restoredPhotos) > 1 ? 's' : '' }} prête{{ count($restoredPhotos) > 1 ? 's' : '' }} à uploader</p>
                @endif

                {{-- Prix final --}}
                <div class="mb-4">
                    <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-1.5">
                        LE PRIX HT EST DE <span class="text-[#F5F0E8]" x-text="(finalHt / 1.2).toFixed(2).replace('.', ',') + ' €'">
                            {{ number_format((float)$finalPrice / 1.2, 2, ',', ' ') }} €
                        </span>
                    </label>
                    <div class="flex items-center gap-3">
                        <input wire:model="finalPrice" type="number" step="0.01" min="0"
                               @input="finalHt = parseFloat($event.target.value) || 0"
                               class="w-36 bg-[#1A1510] border border-[#C9A84C]/20 text-[#C9A84C] font-bold text-lg text-center rounded-sm px-4 py-2 focus:outline-none focus:border-[#C9A84C]/60 transition-all">
                        <div class="text-sm">
                            <p class="text-[#7A6E5E]">€ TTC (Facturé)</p>
                            <p class="text-[#7A6E5E]/60 text-xs mt-0.5">IA suggérait : {{ number_format($order->getAmountTtcCents() / 100, 2, ',', ' ') }} € TTC</p>
                        </div>
                    </div>
                    @error('finalPrice') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled"
                        class="w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-sm transition-all hover:shadow-[0_0_20px_rgba(59,130,246,0.3)]">
                    <span wire:loading.remove wire:target="uploadRestoredPhotos">
                        ✓ Uploader les photos
                    </span>
                    <span wire:loading wire:target="uploadRestoredPhotos" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Upload en cours…
                    </span>
                </button>
            </form>
            @endif

            {{-- === PHOTOS RESTAURÉES — lecture seule (le client valide depuis son espace) === --}}
            @php
                $retouched = $order->getMedia('retouched');
                $activePhotos   = $retouched->filter(fn($m) => ! $m->getCustomProperty('is_rejected'));
                $rejectedPhotos = $retouched->filter(fn($m) => $m->getCustomProperty('is_rejected'));
            @endphp
            @if ($retouched->isNotEmpty())
            <div class="card-glass p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-[#F5F0E8] font-semibold">Photos restaurées</h2>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="text-emerald-400">{{ $activePhotos->count() }} actives</span>
                        @if ($rejectedPhotos->count() > 0)
                        <span class="text-red-400">{{ $rejectedPhotos->count() }} retirées par le client</span>
                        @endif
                    </div>
                </div>
                {{-- Note informative --}}
                <div class="flex items-center gap-2 mb-4 px-3 py-2 bg-[#1A1510] border border-[#C9A84C]/10 rounded-sm">
                    <svg class="w-3.5 h-3.5 text-[#7A6E5E] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-[#7A6E5E] text-xs">Le client sélectionne les photos à conserver depuis son espace client (statut DONE, avant paiement).</p>
                </div>
                {{-- Grille avec Alpine pour le modal de confirmation --}}
                <div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        @foreach ($retouched as $media)
                        @php $isRejected = $media->getCustomProperty('is_rejected', false); @endphp
                        <div x-data="{ h: false }"
                             @mouseenter="h = true"
                             @mouseleave="h = false"
                             class="relative aspect-square bg-[#1A1510] rounded-sm overflow-hidden
                                    {{ $isRejected ? 'border-2 border-red-500/40 opacity-50' : 'border border-emerald-500/20' }}">
                            <img src="{{ route('admin.orders.photo.show', [$order, $media]) }}" class="w-full h-full object-cover">
                            @if ($isRejected)
                            <div class="absolute inset-0 bg-red-900/40 flex items-center justify-center">
                                <span class="text-red-300 text-xs font-bold uppercase tracking-wider bg-red-900/80 px-2 py-0.5 rounded">
                                    Retirée par client
                                </span>
                            </div>
                            @else
                            {{-- Overlay hover : ouvrir dans onglet --}}
                            <a href="{{ route('admin.orders.photo.show', [$order, $media]) }}" target="_blank"
                               x-show="h"
                               class="absolute inset-0 bg-black/60 flex items-center justify-center transition-opacity">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                            {{-- Bouton suppression (visible au survol, statuts avant DONE uniquement) --}}
                            @if (! in_array($order->status, ['DONE', 'PAID', 'DELIVERED']))
                            <button x-show="h"
                                     @click.stop="const wire = $wire; omnyConfirm({
                                         title: 'Supprimer Photo',
                                         message: 'Voulez-vous supprimer définitivement cette photo restaurée ? Cette action est irréversible.',
                                         confirmLabel: '🗑️ Supprimer',
                                         danger: true
                                     }).then(() => wire.deleteRetouchedPhoto({{ $media->id }}))"
                                     title="Supprimer cette photo"
                                     class="absolute top-2 right-2 z-20 w-7 h-7 flex items-center justify-center rounded-full text-xs bg-red-900/90 border border-red-600/60 text-red-300 hover:bg-red-800 shadow-lg transition-colors">
                                 🗑
                             </button>
                            @endif
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>

            </div>
            @endif

            {{-- === STATUT PAIEMENT + LIVRAISON ZIP === --}}
            @if ($order->getMedia('retouched')->isNotEmpty())
            <div class="card-glass overflow-hidden {{ in_array($order->status, ['PAID', 'DELIVERED']) ? 'border border-emerald-500/20' : '' }}"
                 {{ in_array($order->status, ['DONE']) ? 'wire:poll.5000ms="pollPaymentStatus"' : '' }}>

                <div class="px-5 py-4 border-b border-[#C9A84C]/10 flex items-center justify-between">
                    <h2 class="text-[#F5F0E8] font-semibold">Statut paiement &amp; livraison</h2>
                    @if (in_array($order->status, ['PAID', 'DELIVERED']))
                    <span class="flex items-center gap-1.5 px-2.5 py-1 bg-emerald-900/30 border border-emerald-500/30 text-emerald-400 text-xs rounded-full font-medium">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        Paiement effectué
                    </span>
                    @elseif ($order->status === 'DONE')
                    <span class="flex items-center gap-1.5 px-2.5 py-1 bg-yellow-900/20 border border-yellow-500/20 text-yellow-400/80 text-xs rounded-full">
                        <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        En attente du paiement
                    </span>
                    @else
                    <span class="px-2.5 py-1 bg-[#1A1510] border border-[#2A2520] text-[#4A3E2E] text-xs rounded-full">Non applicable</span>
                    @endif
                </div>

                <div class="p-5">
                    @if (in_array($order->status, ['PAID', 'DELIVERED']))
                    {{-- Paiement recu --}}
                    <div class="flex items-start gap-3 mb-5 px-4 py-3.5 bg-emerald-900/15 border border-emerald-500/20 rounded-sm">
                        <svg class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <p class="text-emerald-300 text-sm font-medium">Paiement effectue avec succes</p>
                            @if ($order->paid_at)
                            <p class="text-[#7A6E5E] text-xs mt-0.5">Le {{ $order->paid_at->format('d/m/Y \a H:i') }} &mdash; {{ number_format($order->getAmountTtcCents() / 100, 2, ',', ' ') }}&nbsp;&euro; TTC</p>
                            @endif
                        </div>
                    </div>

                    <p class="text-[#7A6E5E] text-xs mb-3 leading-relaxed">
                        Renvoyez au client le mail contenant son lien de téléchargement (photos HD) et sa facture PDF.
                        <span class="text-[#C9A84C]/60 block mt-0.5">Limite à 1 envoi toutes les 5 minutes.</span>
                    </p>

                    <button wire:click="sendDeliveryEmail"
                            wire:loading.attr="disabled"
                            class="w-full flex items-center justify-center gap-2 px-4 py-3.5
                                   bg-gradient-to-r from-[#C9A84C] to-[#E8C97A]
                                   text-[#0F0C08] font-bold text-sm rounded-sm
                                   hover:opacity-90 transition-all
                                   shadow-[0_4px_20px_rgba(201,168,76,0.25)]
                                   disabled:opacity-50">
                        <span wire:loading.remove wire:target="sendDeliveryEmail" class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            Renvoyer l'email de livraison (lien de téléchargement + facture)
                        </span>
                        <span wire:loading wire:target="sendDeliveryEmail" class="flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Renvoi en cours...
                        </span>
                    </button>

                    @if ($order->status === 'DELIVERED' && $order->delivered_at)
                    <div class="flex items-center justify-center gap-1.5 mt-3">
                        <svg class="w-3 h-3 text-emerald-500/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <p class="text-emerald-400/50 text-[11px]">Livre le {{ $order->delivered_at->format('d/m/Y \a H:i') }}</p>
                    </div>
                    @endif

                    {{-- Bouton de remboursement Stripe --}}
                    <div class="mt-6 pt-5 border-t border-red-500/10" x-data="{ confirmRefund: false }">
                        <button type="button" @click="confirmRefund = true" x-show="!confirmRefund"
                                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-red-900/10 hover:bg-red-900/20 text-red-400 border border-red-500/20 rounded-sm transition-all text-xs font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            Rembourser la commande via Stripe
                        </button>
                        
                        <div x-show="confirmRefund" x-transition class="p-4 bg-red-900/10 border border-red-500/30 rounded-sm">
                            <p class="text-red-400 text-xs font-medium mb-1">⚠️ Le remboursement est immédiat et irréversible.</p>
                            <p class="text-[#7A6E5E] text-xs mb-4">La commande passera en statut "Annulé" et les fichiers seront supprimés définitivement du serveur pour empêcher l'accès client.</p>
                            <div class="flex gap-2">
                                <button type="button" @click="confirmRefund = false"
                                        class="flex-1 py-1.5 text-xs text-[#7A6E5E] hover:text-[#F5F0E8] border border-[#2A2520] hover:border-[#3A3028] rounded-sm transition-all">
                                    Annuler
                                </button>
                                <button wire:click="refundOrder" wire:loading.attr="disabled"
                                        class="flex-1 py-1.5 text-xs bg-red-700/40 hover:bg-red-700/60 text-red-300 border border-red-500/40 rounded-sm transition-all flex items-center justify-center gap-1">
                                    <span wire:loading.remove wire:target="refundOrder">Confirmer Remboursement</span>
                                    <span wire:loading wire:target="refundOrder" class="flex items-center gap-1">
                                        <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        Traitement Stripe...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    @else
                    {{-- Paiement en attente --}}
                    <div class="py-6 text-center">
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <svg class="w-4 h-4 animate-spin text-yellow-400/60" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <p class="text-[#7A6E5E] text-sm">En attente du règlement client…</p>
                        </div>
                        <p class="text-xs text-[#4A3E2E]">La page se mettra à jour automatiquement dès la réception du paiement Stripe.</p>
                    </div>
                    @endif
                </div>
            </div>
            @endif

        </div>

        {{-- ── Sidebar admin ── --}}
        <div class="space-y-5">

            {{-- Détails --}}
            <div class="card-glass p-5">
                <h3 class="text-[#C9A84C] text-xs tracking-widest uppercase border-b border-[#C9A84C]/20 pb-2 mb-4 font-semibold">D&eacute;tails commande</h3>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between"><dt class="text-[#7A6E5E]">Photos</dt><dd class="text-[#F5F0E8]">{{ $order->photo_count }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[#7A6E5E]">Niveau IA</dt>
                        <dd class="{{ $order->damage_level === 'heavy' ? 'text-orange-400' : ($order->damage_level === 'medium' ? 'text-amber-400' : 'text-emerald-400') }} font-medium">
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
                                        'light'  => 'Standard — 1 € TTC/photo',
                                        'medium' => 'Avancée — 2 € TTC/photo',
                                        'heavy'  => 'Complète — 3 € TTC/photo',
                                        default  => ucfirst($order->damage_level ?? 'N/A'),
                                    };
                                }
                            @endphp
                            {{ $lvlLabel }}
                        </dd>
                    </div>
                    {{-- Calcul des prix unifié via le modèle ou la saisie en cours --}}
                    @php
                        // Priorité à la saisie en cours dans le formulaire (réactivité)
                        if (isset($finalPrice) && is_numeric($finalPrice) && $finalPrice > 0) {
                            $ttcC = (int) round((float)$finalPrice * 100);
                        } else {
                            $ttcC = $order->getAmountTtcCents();
                        }
                        
                        $htC  = (int) round($ttcC / 1.2);
                        $tvaC = max(0, $ttcC - $htC);
                        $discountC = $order->discount_cents ?? 0;
                    @endphp

                    <div class="border-t border-[#C9A84C]/10 pt-4 space-y-1.5">
                        <div class="flex justify-between"><dt class="text-[#7A6E5E]">HT Net</dt><dd class="text-[#F5F0E8]">{{ number_format($htC / 100, 2, ',', ' ') }} €</dd></div>
                        <div class="flex justify-between"><dt class="text-[#7A6E5E]">TVA 20%</dt><dd class="text-[#F5F0E8]">{{ number_format($tvaC / 100, 2, ',', ' ') }} €</dd></div>
                        <div class="flex justify-between font-bold"><dt class="text-[#C9A84C]">TOTAL TTC</dt><dd class="text-[#C9A84C]">{{ number_format($ttcC / 100, 2, ',', ' ') }} €</dd></div>
                    </div>
                    @if ($order->paid_at)
                    <div class="flex justify-between border-t border-[#C9A84C]/5 pt-2 mt-2"><dt class="text-[#7A6E5E]">Payé le</dt><dd class="text-emerald-400 text-xs">{{ $order->paid_at->format('d/m/Y H:i') }}</dd></div>
                    <div class="mt-4 pt-4 border-t border-[#C9A84C]/10">
                        <a href="{{ route('admin.orders.invoice', $order) }}" target="_blank"
                           class="w-full flex items-center justify-center gap-2 px-3 py-2 bg-[#C9A84C]/10 hover:bg-[#C9A84C]/20 border border-[#C9A84C]/30 text-[#C9A84C] hover:text-[#E8C97A] text-xs font-medium rounded-sm transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Télécharger la facture PDF
                        </a>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- Notes + prix rapide --}}
            <div class="card-glass p-5">
                <h3 class="text-[#C9A84C] text-xs tracking-widest uppercase border-b border-[#C9A84C]/20 pb-2 mb-4 font-semibold">Notes internes</h3>
                <textarea wire:model="adminNotes" rows="4" placeholder="Notes visibles uniquement par l'équipe…"
                          class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-xs rounded-sm px-3 py-2 placeholder-[#7A6E5E]/50 resize-none focus:outline-none focus:border-[#C9A84C]/60 transition-all mb-3">
                </textarea>
                @if (!in_array($order->status, ['PAID', 'DELIVERED', 'CANCELLED']))
                <div class="mb-3">
                    <div class="flex gap-2 mb-1">
                        <input wire:model="finalPrice" type="number" step="0.01" placeholder="Prix TTC (€)"
                               @input="finalTtc = parseFloat($event.target.value) || 0"
                               class="flex-1 bg-[#1A1510] border border-[#C9A84C]/20 text-[#C9A84C] text-sm rounded-sm px-3 py-2 focus:outline-none focus:border-[#C9A84C]/60 transition-all">
                        <button wire:click="saveNotes" class="px-4 py-2 text-xs bg-[#C9A84C]/20 text-[#C9A84C] border border-[#C9A84C]/30 hover:bg-[#C9A84C]/30 rounded-sm transition-all">
                            Sauver
                        </button>
                    </div>
                    <p class="text-[#7A6E5E] text-xs">
                        TTC net client (TVA 20% incluse)
                    </p>
                </div>
                @endif
            </div>

            {{-- Urgence Légale (CSAM / NSFW) --}}
            @if ($order->status === 'FLAGGED')
            <div class="card-glass p-5 border-red-500 bg-red-950/20">
                <h3 class="text-red-400 text-xs tracking-widest uppercase border-b border-red-500/20 pb-2 mb-4 font-bold flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Alerte Modération
                </h3>
                <p class="text-[#F5F0E8] text-sm mb-4">L'IA a signalé du contenu sensible/illégal. Veuillez vérifier les images (floutées par défaut).</p>
                
                {{-- Lexique des catégories --}}
                <div x-data="{ openGlossary: false }" class="mb-4">
                    <button type="button" @click="openGlossary = !openGlossary" class="flex items-center gap-1 text-xs text-red-400 hover:text-red-300 transition-colors">
                        <svg class="w-3 h-3 transition-transform" :class="openGlossary ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        Voir le lexique des catégories (OpenAI Moderation)
                    </button>
                    <div x-show="openGlossary" style="display: none;" class="mt-2 text-[10px] text-red-200/80 bg-red-900/30 p-3 rounded-sm border border-red-500/20 transition-all">
                        <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2">
                            <li><strong class="text-white">sexual</strong> : Contenu sexuellement explicite (pornographie).</li>
                            <li><strong class="text-white">sexual/minors</strong> : 🚨 Pédocriminalité (CSAM). Rapport PHAROS requis.</li>
                            <li><strong class="text-white">hate</strong> : Incitation à la haine (race, genre, religion...).</li>
                            <li><strong class="text-white">hate/threatening</strong> : Haine avec menaces de violence.</li>
                            <li><strong class="text-white">harassment</strong> : Harcèlement (insultes, intimidation).</li>
                            <li><strong class="text-white">harassment/threatening</strong> : Harcèlement avec menaces.</li>
                            <li><strong class="text-white">illicit</strong> : Promotion/instructions d'activités illégales.</li>
                            <li><strong class="text-white">illicit/violent</strong> : Instructions de création d'armes/violence.</li>
                            <li><strong class="text-white">self-harm</strong> : Promotion de l'automutilation ou du suicide.</li>
                            <li><strong class="text-white">self-harm/intent</strong> : Intentions d'automutilation.</li>
                            <li><strong class="text-white">self-harm/instructions</strong> : Instructions sur comment s'automutiler.</li>
                            <li><strong class="text-white">violence</strong> : Contenu promouvant ou incitant à la violence.</li>
                            <li><strong class="text-white">violence/graphic</strong> : Images gores, macabres ou choquantes.</li>
                        </ul>
                    </div>
                </div>

                <div class="space-y-3">
                    <button wire:click="ignoreReport" wire:confirm="Êtes-vous sûr qu'il s'agit d'un faux positif ?" class="w-full py-2 px-3 text-xs bg-[#1A1510] text-[#7A6E5E] border border-[#7A6E5E]/30 hover:text-[#F5F0E8] hover:border-[#F5F0E8] rounded-sm transition-all text-left">
                        1. Faux Positif : Ignorer
                    </button>
                    <button wire:click="banAndDestroy" wire:confirm="Action IRRÉVERSIBLE : Les photos seront physiquement détruites et l'utilisateur banni. Confirmer ?" class="w-full py-2 px-3 text-xs bg-red-900/30 text-red-400 border border-red-500/50 hover:bg-red-900 hover:text-white rounded-sm transition-all text-left font-bold">
                        2. BANNIR ET DÉTRUIRE (CGU)
                    </button>
                    <button wire:click="generatePharosReport" class="w-full py-2 px-3 text-xs bg-red-600 text-white border border-red-500 hover:bg-red-500 rounded-sm transition-all text-left font-bold flex justify-between items-center">
                        <span>3. RAPPORT LÉGAL (PHAROS)</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    </button>
                    <p class="text-xs text-red-400/80 mt-2">Le rapport PHAROS contient l'IP et l'email pour dépôt sur internet-signalement.gouv.fr</p>
                </div>
            </div>
            @endif

            {{-- Annulation --}}
            @if (in_array($order->status, ['PENDING', 'IN_PROGRESS', 'FLAGGED']))
            <div class="card-glass p-5 border-red-500/15" x-data="{ open: false, confirmCancel: false }">
                <button @click="open = !open; confirmCancel = false" class="w-full flex items-center justify-between text-red-400 text-xs hover:text-red-300 transition-colors">
                    <span class="font-medium">Annuler la commande</span>
                    <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-transition class="mt-3">
                    <textarea wire:model="cancelReason" rows="3" placeholder="Raison (obligatoire, min. 10 caractères)…"
                              class="w-full bg-[#1A1510] border border-red-500/20 text-[#F5F0E8] text-xs rounded-sm px-3 py-2 placeholder-[#7A6E5E]/50 resize-none focus:outline-none focus:border-red-500/50 transition-all mb-2">
                    </textarea>
                    @error('cancelReason') <p class="text-red-400 text-xs mb-2">{{ $message }}</p> @enderror

                    {{-- Bouton → ouvre confirmation inline --}}
                    <button type="button"
                            @click="confirmCancel = true"
                            x-show="!confirmCancel"
                            class="w-full py-2 text-xs bg-red-900/30 text-red-400 border border-red-500/30 hover:bg-red-900/50 rounded-sm transition-all">
                        Confirmer l'annulation
                    </button>

                    {{-- Confirmation inline custom --}}
                    <div x-show="confirmCancel" x-transition
                         class="mt-3 p-4 bg-red-900/10 border border-red-500/20 rounded-sm">
                        <p class="text-red-400 text-xs font-medium mb-1">⚠️ Cette action est irréversible.</p>
                        <p class="text-[#7A6E5E] text-xs mb-4">La commande sera annulée et le client sera notifié.</p>
                        <div class="flex gap-2">
                            <button type="button"
                                    @click="confirmCancel = false"
                                    class="flex-1 py-1.5 text-xs text-[#7A6E5E] hover:text-[#F5F0E8] border border-[#2A2520] hover:border-[#3A3028] rounded-sm transition-all">
                                Retour
                            </button>
                            <button wire:click="cancelOrder"
                                    wire:loading.attr="disabled"
                                    class="flex-1 py-1.5 text-xs bg-red-700/40 hover:bg-red-700/60 text-red-300 border border-red-500/40 rounded-sm transition-all flex items-center justify-center gap-1">
                                <span wire:loading.remove wire:target="cancelOrder">🗑️ Oui, annuler</span>
                                <span wire:loading wire:target="cancelOrder" class="flex items-center gap-1">
                                    <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    Annulation...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- 📧 Notification client (Déclenchable si photos présentes) --}}
            @if (in_array($order->status, ['IN_PROGRESS', 'DONE']) && $order->getMedia('retouched')->isNotEmpty())
            <div class="card-glass p-5 border-[#C9A84C]/20">
                <h3 class="text-[#C9A84C] text-xs tracking-widest uppercase border-b border-[#C9A84C]/20 pb-2 mb-4 font-semibold">Notifier le client</h3>
                <p class="text-[#7A6E5E] text-xs mb-3 leading-relaxed">
                    @if ($order->status === 'IN_PROGRESS')
                        Les photos sont uploadées. Cliquez pour <strong>finaliser</strong> la commande et envoyer l'email de validation au client.
                    @else
                        L'email contient le lien sécurisé vers les photos restaurées pour validation et paiement.<br>
                        <span class="text-[#C9A84C]/70">Limité à 1 envoi toutes les 5 minutes.</span>
                    @endif
                </p>
                <button wire:click="notifyClient"
                        wire:loading.attr="disabled"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-[#C9A84C]/15 hover:bg-[#C9A84C]/25 border border-[#C9A84C]/30 hover:border-[#C9A84C]/50 text-[#C9A84C] text-sm rounded-sm transition-all">
                    <span wire:loading.remove wire:target="notifyClient" class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        {{ $order->status === 'IN_PROGRESS' ? "Finaliser et envoyer l'email" : "Renvoyer l'email de validation" }}
                    </span>
                    <span wire:loading wire:target="notifyClient" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 12h4z"/></svg>
                        Traitement…
                    </span>
                </button>
                @if ($order->preview_unlocked_at)
                <p class="text-emerald-400/70 text-[11px] mt-2 text-center">
                    ✓ Client a déjà accédé à l'aperçu
                    <span class="text-[#7A6E5E]">({{ $order->preview_unlocked_at->format('d/m H:i') }})</span>
                </p>
                @endif
            </div>
            @endif

            {{-- ⭐ Avis client --}}
            @if ($order->testimonial)
            @php $t = $order->testimonial; @endphp
            <div class="card-glass p-5 border-yellow-500/15">
                <h3 class="text-[#C9A84C] text-xs tracking-widest uppercase border-b border-[#C9A84C]/20 pb-2 mb-4 font-semibold flex items-center justify-between">
                    Avis client
                    <span class="px-2 py-0.5 text-[10px] rounded-full border {{ $t->is_published ? 'bg-emerald-900/30 text-emerald-400 border-emerald-500/30' : 'bg-yellow-900/20 text-yellow-400/80 border-yellow-500/20' }}">
                        {{ $t->is_published ? 'Publié' : 'En attente' }}
                    </span>
                </h3>
                {{-- Étoiles --}}
                <div class="flex gap-0.5 mb-2">
                    @for ($i = 1; $i <= 5; $i++)
                    <svg class="w-4 h-4 {{ $i <= $t->rating ? 'text-[#C9A84C]' : 'text-[#3A3028]' }}" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    @endfor
                    <span class="text-[#7A6E5E] text-xs ml-1.5 self-center">{{ $t->rating }}/5</span>
                </div>
                <p class="text-[#F5F0E8] text-xs leading-relaxed italic">"{{ $t->content }}"</p>
                <p class="text-[#7A6E5E] text-[11px] mt-2">— {{ $t->author_name }}</p>
            </div>
            @endif

            {{-- Audit log --}}
            @if ($order->auditLogs->isNotEmpty())
            <div class="card-glass p-5">
                <h3 class="text-[#C9A84C] text-xs tracking-widest uppercase border-b border-[#C9A84C]/20 pb-2 mb-3 font-semibold">Historique</h3>
                <div class="space-y-2.5">
                    @foreach ($order->auditLogs->sortByDesc('created_at')->take(8) as $log)
                    @php
                        $actionLabels = [
                            'ORDER_CREATED'        => '✦ Commande créée',
                            'ORDER_STATUS_CHANGED' => '➤ Statut modifié',
                            'ORDER_CANCELLED'      => '✕ Commande annulée',
                            'ORDER_UPDATED'        => '✎ Commande modifiée',
                            'ORDER_PAID'           => '✔ Paiement reçu',
                            'ORDER_DELIVERED'      => '📦 Photos livrées',
                            'COUPON_USED'          => '🏷️ Coupon appliqué',
                            'DOWNLOAD_INITIATED'   => '⬇ Téléchargement initié',
                        ];
                        $label = $actionLabels[$log->action] ?? $log->action;
                        // Si meta contient from/to, afficher la transition
                        $meta  = is_array($log->meta) ? $log->meta : (json_decode($log->meta ?? '{}', true) ?? []);
                        if (!empty($meta['from']) && !empty($meta['to'])) {
                            $statusFr = ['PENDING'=>'En attente','IN_PROGRESS'=>'En cours','DONE'=>'Terminé','PAID'=>'Payé','DELIVERED'=>'Livré','CANCELLED'=>'Annulé','FLAGGED'=>'Signalé'];
                            $label .= ' : ' . ($statusFr[$meta['from']] ?? $meta['from']) . ' → ' . ($statusFr[$meta['to']] ?? $meta['to']);
                        }
                    @endphp
                    <div class="flex items-start gap-2 text-xs">
                        <div class="w-1.5 h-1.5 rounded-full bg-[#C9A84C]/40 mt-1.5 shrink-0"></div>
                        <div>
                            <p class="text-[#F5F0E8]">{{ $label }}</p>
                            <p class="text-[#7A6E5E]">{{ $log->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>
    </div>
</div>

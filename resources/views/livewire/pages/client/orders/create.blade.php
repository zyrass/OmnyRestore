<?php
/**
 * Client — Formulaire de création de commande avec analyse IA
 * Route: GET /client/orders/create
 *
 * Flow amélioré :
 *   1. Client sélectionne 1-N photos (jusqu'à 20)
 *   2. Après sélection → analyse IA automatique (GPT-4o Vision)
 *   3. L'IA détermine le niveau de dommage et affiche son verdict + prix
 *   4. Le client peut consulter le verdict mais PAS changer le prix
 *      (évite la fraude : choisir "standard" pour une photo très abîmée)
 *   5. Le client peut ajouter des instructions optionnelles
 *   6. Soumission → création Order (PENDING) + upload médias
 */

use App\Http\Requests\Client\CreateOrderRequest;
use App\Models\Order;
use App\Services\AuditService;
use App\Services\CouponService;
use App\Services\PhotoDamageAnalyzer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new
#[Layout('layouts.app')]
#[Title('Nouvelle commande')]
class extends Component
{
    use WithFileUploads;

    public array $photos = [];

    /** Résultats d'analyse IA par index de photo */
    public array $analysisResults = [];

    /** true quand l'analyse est en cours */
    public bool $analyzing = false;

    /** true quand l'analyse est terminée */
    public bool $analysisComplete = false;

    /** Niveau de dommage global déterminé par l'IA (worst-case parmi toutes les photos) */
    public string $damage_level = 'light';

    /** Instructions optionnelles du client */
    public ?string $instructions = null;

    /** Code coupon saisi par le client */
    public string $couponCode = '';

    /** Résultat de l'application du coupon (null = pas encore appliqué) */
    public ?array $couponResult = null;

    /** Propriété temporaire pour le wire:model de l'input file (évite d'écraser $photos) */
    public $newPhotos = [];

    /**
     * Déclenché quand de nouvelles photos sont sélectionnées.
     * On les AJOUTE à la liste existante au lieu de les remplacer.
     */
    public function updatedNewPhotos(): void
    {
        if (empty($this->newPhotos)) return;

        // Conversion en tableau si Livewire envoie un seul fichier
        $incoming = is_array($this->newPhotos) ? $this->newPhotos : [$this->newPhotos];

        // Vérification de la limite globale (20 photos)
        if ((count($this->photos) + count($incoming)) > 20) {
            $this->addError('photos', 'Vous pouvez envoyer au maximum 20 photos par commande.');
            $this->newPhotos = [];
            return;
        }

        $this->analyzing        = true;
        $this->analysisComplete = false;

        $analyzer = app(PhotoDamageAnalyzer::class);
        $levelPriority = ['light' => 0, 'medium' => 1, 'heavy' => 2];
        $worstLevel = $this->damage_level;

        try {
            foreach ($incoming as $photo) {
                // Ajouter à la collection principale
                $this->photos[] = $photo;
                $i = count($this->photos) - 1;

                // Analyser seulement la nouvelle photo
                $result = $analyzer->analyze($photo);
                $this->analysisResults[$i] = $result;

                // Update worst-case global
                if (($levelPriority[$result['level']] ?? 0) > ($levelPriority[$worstLevel] ?? 0)) {
                    $worstLevel = $result['level'];
                }
            }
        } finally {
            $this->damage_level     = $worstLevel;
            $this->analyzing        = false;
            $this->analysisComplete = true;
            $this->newPhotos        = []; // Reset l'input file pour la prochaine sélection
        }

        // Réinitialiser le coupon si les photos changent
        $this->couponResult = null;
        $this->couponCode   = '';
    }

    /**
     * Calcule le prix HT de base (en centimes) : SOMME individuelle par photo.
     * Chaque photo est tarifée selon son propre niveau d’analyse IA.
     */
    private function baseHtCents(): int
    {
        if (empty($this->analysisResults)) {
            // Fallback avant analyse : prix standard minimum (HT)
            return count($this->photos) * (PhotoDamageAnalyzer::PRICES['light'] ?? 83);
        }
        // Somme des prix HT individuels de chaque photo
        return (int) array_sum(array_column($this->analysisResults, 'price_cents'));
    }

    /**
     * Calcule le prix TTC de base (en centimes) en sommant les TTC individuels.
     * ⚠️ NE PAS faire sum(HT) * 1.20 — perte de 1 centime possible par arrondi.
     * On somme directement les price_ttc_cents de chaque photo.
     */
    private function baseTtcCents(): int
    {
        if (empty($this->analysisResults)) {
            return count($this->photos) * (PhotoDamageAnalyzer::PRICES_TTC['light'] ?? 100);
        }
        return (int) array_sum(array_column($this->analysisResults, 'price_ttc_cents'));
    }

    /**
     * Applique un code coupon.
     * Appelé via wire:click ou x-on:submit sur le formulaire coupon.
     */
    public function applyCoupon(CouponService $couponService): void
    {
        // Règles extraites de CreateOrderRequest::couponRules() pour centralisation
        $this->validate(
            CreateOrderRequest::couponRules(),
            ['couponCode.required' => 'Veuillez saisir un code de réduction.',
             'couponCode.min'      => 'Le code doit contenir au moins :min caractères.']
        );
        $this->couponResult = $couponService->apply(
            $this->couponCode,
            $this->baseHtCents(),
            auth()->user()
        );
    }

    /**
     * Applique automatiquement un coupon de fidélité sélectionné.
     */
    public function selectLoyaltyCoupon(string $code): void
    {
        $this->couponCode = $code;
        $this->couponResult = app(CouponService::class)->apply(
            $code,
            $this->baseHtCents(),
            auth()->user()
        );
    }

    /**
     * Récupère tous les coupons de fidélité disponibles de l'utilisateur.
     */
    public function getAvailableCoupons()
    {
        return auth()->check()
            ? app(\App\Services\LoyaltyService::class)->getAvailableCoupons(auth()->user())
            : collect();
    }

    /**
     * Supprime le coupon appliqué.
     */
    public function removeCoupon(): void
    {
        $this->couponResult = null;
        $this->couponCode   = '';
    }

    /**
     * Supprime une photo de la sélection.
     */
    public function removePhoto(int $index): void
    {
        if (isset($this->photos[$index])) {
            unset($this->photos[$index]);
            $this->photos = array_values($this->photos);
        }

        if (isset($this->analysisResults[$index])) {
            unset($this->analysisResults[$index]);
            $this->analysisResults = array_values($this->analysisResults);
        }

        // Recalculer le pire niveau de dommage
        $levelPriority = ['light' => 0, 'medium' => 1, 'heavy' => 2];
        $worstLevel = 'light';
        foreach ($this->analysisResults as $res) {
            if (($levelPriority[$res['level']] ?? 0) > ($levelPriority[$worstLevel] ?? 0)) {
                $worstLevel = $res['level'];
            }
        }
        $this->damage_level = $worstLevel;

        // Réinitialiser le coupon
        $this->couponResult = null;
        $this->couponCode   = '';

        if (count($this->photos) === 0) {
            $this->analysisComplete = false;
        }
    }

    /**
     * Valide et soumet la commande.
     */
    public function submit(AuditService $audit, CouponService $couponService): void
    {
        // Centralisation des règles de validation via CreateOrderRequest
        // Note : photos.* n'est pas re-validé ici — les fichiers temporaires Livewire
        // sont déjà validés à l'upload (wire:model) et peuvent être dans un état
        // transitoire. La validation complète s'applique via updatedPhotos().
        $request = new CreateOrderRequest;
        $this->validate(
            array_intersect_key($request->rules(), array_flip(['photos', 'instructions'])),
            $request->messages()
        );

        \Illuminate\Support\Facades\Log::info('Order submit() déclenché', [
            'user_id'      => auth()->id(),
            'photos_count' => count($this->photos),
            'damage_level' => $this->damage_level,
        ]);

        // Le damage_level est déterminé par l'IA — non modifiable par le client
        $baseTtcCents  = $this->baseTtcCents();
        $discountCents = 0;
        $couponCode    = null;

        // Appliquer le coupon si valide (calcul de la remise directement sur le TTC)
        if ($this->couponResult && $this->couponResult['valid']) {
            $couponCode = strtoupper(trim($this->couponCode));
            $coupon = \App\Models\Coupon::where('code', $couponCode)->first();
            if ($coupon) {
                $discountCents = $coupon->discountTtcCents($baseTtcCents);
            }
        }

        $finalTtcCents = max(0, $baseTtcCents - $discountCents);

        $order = Order::create([
            'user_id'           => auth()->id(),
            'status'            => 'PENDING',
            'photo_count'       => count($this->photos),
            'damage_level'      => $this->damage_level,
            'instructions'      => $this->instructions,
            'base_price_cents'  => $baseTtcCents,         // Prix TTC brut (Source de Vérité)
            'total_price_cents' => $finalTtcCents,        // Prix TTC net (Payé par client)
            'coupon_code'       => $couponCode,
            'discount_cents'    => $discountCents,
            'client_ip'         => request()->ip(),
        ]);

        // Confirmer l'utilisation du coupon (incrémenter le compteur)
        if ($this->couponResult && $this->couponResult['valid'] && $this->couponResult['coupon']) {
            $couponService->confirm($this->couponResult['coupon']);
        }

        // ── Copie des fichiers AVANT que Livewire les supprime ─────────────
        // Les fichiers temporaires Livewire sont supprimés après chaque cycle.
        // On les copie dans un dossier stable avant de les passer à Spatie.
        $tmpDir = storage_path('app/tmp-uploads');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        // ── Vérification préalable : tous les fichiers tmp sont-ils encore présents ? ──
        // Si Livewire a nettoyé certains fichiers tmp entre l'upload et la soumission,
        // on arrête tout plutôt que de créer une commande incomplète.
        $missingFiles = [];
        foreach ($this->photos as $i => $photo) {
            $src = $photo->getRealPath();
            if (! $src || ! file_exists($src)) {
                $missingFiles[] = $photo->getClientOriginalName();
            }
        }

        if (! empty($missingFiles)) {
            // Nettoyer l'ordre créé (évite les commandes PENDING orphelines)
            $order->delete();

            $count = count($missingFiles);
            $this->addError('photos',
                "{$count} photo(s) ont expiré pendant l'analyse (session trop longue ou trop de photos simultanées). ".
                "Veuillez re-sélectionner toutes vos photos et soumettre rapidement."
            );
            \Illuminate\Support\Facades\Log::warning('Order create aborted: tmp files expired', [
                'user_id' => auth()->id(),
                'missing' => $missingFiles,
                'total'   => count($this->photos),
            ]);
            return;
        }

        $uploaded = 0;
        foreach ($this->photos as $i => $photo) {
            $src      = $photo->getRealPath();
            $ext      = strtolower($photo->getClientOriginalExtension() ?: 'jpg');
            // Format standardisé : ORD-2026-0003-IMG-01.jpg
            $indexStr = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
            $safeName = "{$order->reference}-IMG-{$indexStr}.{$ext}";
            $destPath = $tmpDir . '/' . uniqid("orig_{$i}_") . '.' . $ext;

            copy($src, $destPath);

            try {
                $order->addMedia($destPath)
                      ->usingFileName($safeName)
                      ->withCustomProperties([
                          'ai_level'      => $this->analysisResults[$i]['level'] ?? 'unknown',
                          'ai_confidence' => $this->analysisResults[$i]['confidence'] ?? 0,
                      ])
                      ->preservingOriginal()
                      ->toMediaCollection('originals');

                @unlink($destPath);
                $uploaded++;
            } catch (\Throwable $e) {
                @unlink($destPath);
                \Illuminate\Support\Facades\Log::error("Upload photo originale échoué: {$e->getMessage()}", [
                    'order_id' => $order->id,
                    'file'     => $safeName,
                ]);
            }
        }

        \Illuminate\Support\Facades\Log::info('Order created: photos uploaded', [
            'order_id' => $order->id,
            'expected' => count($this->photos),
            'uploaded' => $uploaded,
        ]);

        $audit->orderCreated($order);

        // Lancer l'analyse CSAM/NSFW asynchrone via OpenAI
        \App\Jobs\AnalyzeOrderSafetyJob::dispatch($order);

        $this->redirect(route('client.orders.show', $order), navigate: true);
    }
}; ?>

<div>
    {{-- En-tête --}}
    <div class="mb-8 flex items-center gap-4">
        <a href="{{ route('client.orders.index') }}" wire:navigate class="text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-[#F5F0E8]">Nouvelle commande</h1>
            <p class="text-[#7A6E5E] text-sm mt-1">Déposez vos photos — le tarif est estimé automatiquement selon le niveau de restauration détecté.</p>
        </div>
    </div>

    <form wire:submit="submit">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- ── Colonne principale ── --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Zone upload --}}
                <div class="card-glass p-6">
                    <h2 class="text-[#F5F0E8] font-semibold mb-1">Vos photos à restaurer</h2>
                    <p class="text-[#7A6E5E] text-sm mb-5">JPEG, PNG ou TIFF &mdash; 20 Mo max par photo &mdash; jusqu'&agrave; 20 photos</p>

                    <label for="photos-input"
                           class="block border-2 border-dashed border-[#C9A84C]/20 hover:border-[#C9A84C]/50 rounded-sm p-10 text-center cursor-pointer transition-all duration-300 hover:bg-[#C9A84C]/3"
                           x-data="{ dragging: false }"
                           @dragover.prevent="dragging = true" @dragleave="dragging = false" @drop.prevent="dragging = false"
                           :class="dragging ? 'border-[#C9A84C]/70 bg-[#C9A84C]/5' : ''">
                        <svg class="w-12 h-12 text-[#C9A84C]/30 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="text-[#F5F0E8] text-sm font-medium mb-1">Glissez vos photos ici</p>
                        <p class="text-[#7A6E5E] text-xs">ou <span class="text-[#C9A84C] underline">cliquez pour sélectionner</span></p>
                        <input id="photos-input" type="file" wire:model.live="newPhotos" multiple accept=".jpg,.jpeg,.png,.tiff,.tif" class="hidden">
                    </label>

                    @error('photos') <p class="text-red-400 text-sm mt-2 font-medium">&#9888; {{ $message }}</p> @enderror
                    @error('photos.*') <p class="text-red-400 text-xs mt-2">{{ $message }}</p> @enderror

                    {{-- Loading upload --}}
                    <div wire:loading wire:target="newPhotos" class="mt-4 flex items-center gap-2 text-[#7A6E5E] text-sm">
                        <svg class="animate-spin w-4 h-4 text-[#C9A84C]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Chargement des photos&hellip; estimation du tarif en cours&hellip;
                    </div>

                    {{-- Grille photos + résultats IA --}}
                    @if (count($photos) > 0)
                    <div class="mt-5 space-y-3">

                        {{-- Banner analyse en cours --}}
                        @if ($analyzing)
                        <div class="flex items-center gap-3 bg-[#C9A84C]/10 border border-[#C9A84C]/25 rounded-sm px-4 py-3">
                            <svg class="animate-spin w-4 h-4 text-[#C9A84C] shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <div>
                                <p class="text-[#C9A84C] text-sm font-medium">Analyse en cours&hellip;</p>
                                <p class="text-[#7A6E5E] text-sm font-light">Chaque photo est examinée en temps réel pour estimer le tarif selon son niveau de restauration.</p>
                            </div>
                        </div>
                        @endif

                        {{-- Liste des photos en format Lignes Todo-List Premium --}}
                        <div class="space-y-3">
                            @foreach ($photos as $i => $photo)
                            <div class="relative flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 bg-[#1A1510]/40 rounded-sm border border-[#C9A84C]/15 hover:border-[#C9A84C]/45 transition-all duration-300 gap-4">
                                
                                {{-- Thumbnail & Métadonnées --}}
                                <div class="flex items-center gap-4 min-w-0 flex-1 w-full">
                                    <div class="w-16 h-16 shrink-0 aspect-square bg-[#1A1510] rounded-sm overflow-hidden border border-[#C9A84C]/20 group">
                                        <img src="{{ $photo->temporaryUrl() }}" alt="Photo {{ $i + 1 }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                    </div>
                                    
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="text-[#F5F0E8] text-sm font-semibold truncate">{{ $photo->getClientOriginalName() }}</p>
                                            <span class="text-[10px] text-[#7A6E5E] shrink-0 font-medium">({{ round($photo->getSize() / 1024 / 1024, 2) }} Mo)</span>
                                        </div>
                                        @if (isset($analysisResults[$i]))
                                            <p class="text-xs text-[#7A6E5E] mt-1 leading-relaxed">{{ $analysisResults[$i]['reason'] }}</p>
                                        @elseif ($analyzing)
                                            <div class="flex items-center gap-2 text-[#7A6E5E] text-xs mt-1">
                                                <svg class="animate-spin w-3 h-3 text-[#C9A84C]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                <span class="italic">Estimation du tarif par l'IA en cours...</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Verdict IA, Jauge & Prix --}}
                                <div class="shrink-0 flex items-center justify-between sm:justify-end gap-6 w-full sm:w-auto pt-3 sm:pt-0 border-t sm:border-t-0 border-[#C9A84C]/10">
                                    
                                    {{-- Verdict & Jauge --}}
                                    @if (isset($analysisResults[$i]))
                                        @php $result = $analysisResults[$i]; @endphp
                                        @php
                                        $lvlCfg = match($result['level']) {
                                            'heavy'  => [
                                                'bg'    => 'bg-orange-950/40 border border-orange-500/20',
                                                'text'  => 'text-orange-400',
                                                'bar'   => 'bg-orange-400',
                                                'label' => 'Restauration Complète',
                                                'price' => '3,00 €',
                                            ],
                                            'medium' => [
                                                'bg'    => 'bg-amber-950/40 border border-amber-500/20',
                                                'text'  => 'text-amber-400',
                                                'bar'   => 'bg-amber-400',
                                                'label' => 'Restauration Avancée',
                                                'price' => '2,00 €',
                                            ],
                                            default  => [
                                                'bg'    => 'bg-emerald-950/40 border border-emerald-500/20',
                                                'text'  => 'text-emerald-400',
                                                'bar'   => 'bg-emerald-400',
                                                'label' => 'Restauration Standard',
                                                'price' => '1,00 €',
                                            ],
                                        };
                                        @endphp
                                        
                                        <div class="flex flex-col items-start sm:items-end gap-1 min-w-[130px]">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-semibold border tracking-wider uppercase {{ $lvlCfg['bg'] }} {{ $lvlCfg['text'] }}">
                                                {{ $lvlCfg['label'] }}
                                            </span>
                                            
                                            {{-- Confiance --}}
                                            <div class="flex items-center gap-1.5 w-24">
                                                <div class="h-1 bg-[#1A1510] rounded-full overflow-hidden flex-1">
                                                    <div class="h-full rounded-full {{ $lvlCfg['bar'] }}" style="width: {{ $result['confidence'] }}%"></div>
                                                </div>
                                                <span class="text-[9px] text-[#7A6E5E] font-medium">{{ $result['confidence'] }}%</span>
                                            </div>
                                        </div>

                                        {{-- Prix --}}
                                        <div class="text-right min-w-[70px] pl-4 border-l border-[#C9A84C]/10">
                                            <span class="text-base font-bold text-[#F5F0E8]">{{ $lvlCfg['price'] }}</span>
                                            <span class="block text-[8px] text-[#7A6E5E] tracking-wider uppercase">TTC</span>
                                        </div>
                                    @else
                                        <div class="text-right min-w-[70px]">
                                            <span class="text-sm text-[#7A6E5E] italic">Calcul...</span>
                                        </div>
                                    @endif

                                    {{-- Actions (Supprimer) --}}
                                    <div class="pl-2">
                                        <button type="button" 
                                                wire:click="removePhoto({{ $i }})"
                                                class="bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 hover:border-red-500/40 rounded-sm p-1.5 transition-all duration-200"
                                                title="Supprimer la photo">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                        {{-- Verdict global IA --}}
                        @if ($analysisComplete)
                        <div class="flex items-start gap-3 p-4 rounded-sm border
                            {{ $damage_level === 'heavy'
                                ? 'bg-orange-950/30 border-orange-500/30'
                                : 'bg-emerald-950/30 border-emerald-500/30' }}">
                        @if ($damage_level === 'heavy')
                            <svg class="w-5 h-5 text-orange-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <div>
                                <p class="text-orange-400 font-semibold text-sm">Restauration Compl&egrave;te d&eacute;tect&eacute;e</p>
                                <p class="text-[#7A6E5E] text-sm mt-0.5">Au moins une photo pr&eacute;sente des dommages importants (3&euro;). Chaque photo est factur&eacute;e <strong class="text-orange-400">individuellement selon son niveau</strong>.</p>
                            </div>
                        @elseif ($damage_level === 'medium')
                            <svg class="w-5 h-5 text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <div>
                                <p class="text-amber-400 font-semibold text-sm">Restauration Avanc&eacute;e applicable</p>
                                <p class="text-[#7A6E5E] text-sm mt-0.5">Usure mod&eacute;r&eacute;e &agrave; avanc&eacute;e d&eacute;tect&eacute;e. Chaque photo est factur&eacute;e <strong class="text-amber-400">individuellement selon son niveau</strong> (1&euro;, 2&euro; ou 3&euro;).</p>
                            </div>
                        @else
                            <svg class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div>
                                <p class="text-emerald-400 font-semibold text-sm">Restauration Standard applicable</p>
                                <p class="text-[#7A6E5E] text-sm mt-0.5">Toutes vos photos sont en bon &eacute;tat. Le tarif appliqu&eacute; sera de <strong class="text-emerald-400">1&euro; / photo</strong>.</p>
                            </div>
                        @endif
                        </div>

                        {{-- Note transparence --}}
                        <div class="flex items-start gap-2 text-[#7A6E5E] text-sm">
                            <svg class="w-4 h-4 text-[#C9A84C] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Le tarif est estim&eacute; automatiquement selon le niveau de restauration d&eacute;tect&eacute;. Notre &eacute;quipe peut r&eacute;viser ce tarif apr&egrave;s examen manuel si vous le contestez.</span>
                        </div>
                        @endif
                    @endif
                </div>

                {{-- Instructions optionnelles --}}
                <div class="card-glass p-6">
                    <h2 class="text-[#F5F0E8] font-semibold mb-1">Instructions (optionnel)</h2>
                    <p class="text-[#7A6E5E] text-sm mb-4">Zones &agrave; pr&eacute;server, contexte de la photo, personnes importantes&hellip;</p>
                    <textarea wire:model="instructions" id="instructions" rows="4"
                              placeholder="Ex : Photo de mariage 1967. La robe de mariée doit être blanche. Préserver les visages à gauche…"
                              class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm px-4 py-3
                                     placeholder-[#7A6E5E]/50 resize-none
                                     focus:outline-none focus:border-[#C9A84C]/60 focus:ring-1 focus:ring-[#C9A84C]/30 transition-all duration-200">
                    </textarea>
                    <x-input-error :messages="$errors->get('instructions')" class="mt-1.5" />
                </div>
            </div>

            {{-- ── Sidebar récap ── --}}
            <div class="space-y-6">

                {{-- Récapitulatif IA --}}
                <div class="card-glass p-6">
                    <h2 class="text-[#F5F0E8] font-semibold mb-4">Récapitulatif</h2>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-[#7A6E5E]">Photos</span>
                            <span class="text-[#F5F0E8]">{{ count($photos) > 0 ? count($photos) : '—' }}</span>
                        </div>
                        <div class="flex justify-between items-start">
                            <span class="text-[#7A6E5E]">Tarif/photo</span>
                            <span class="text-right">
                                @if ($analysisComplete)
                                    @php
                                        $allLevels   = array_column($this->analysisResults, 'level');
                                        $uniqueLevels = array_unique($allLevels);
                                        $isMixed     = count($uniqueLevels) > 1;
                                        if ($isMixed) {
                                            $priceLabel = 'Variable';
                                            $priceColor = 'text-amber-400';
                                            $priceSub   = '1&euro; &agrave; 3&euro; selon &eacute;tat';
                                        } else {
                                            $priceLabel = match($damage_level) {
                                                'medium' => '2,00 &euro;',
                                                'heavy'  => '3,00 &euro;',
                                                default  => '1,00 &euro;',
                                            };
                                            $priceColor = match($damage_level) {
                                                'medium' => 'text-amber-400',
                                                'heavy'  => 'text-orange-400',
                                                default  => 'text-emerald-400',
                                            };
                                            $priceSub = 'estim&eacute; selon &eacute;tat';
                                        }
                                    @endphp
                                    <span class="{{ $priceColor }} font-semibold">{!! $priceLabel !!}</span><br>
                                    <span class="text-[10px] text-[#7A6E5E]">{!! $priceSub !!}</span>
                                @elseif ($analyzing)
                                    <svg class="animate-spin w-3.5 h-3.5 text-[#C9A84C] inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                @else
                                    <span class="text-[#7A6E5E] text-xs">après analyse</span>
                                @endif
                            </span>
                        </div>
                        @if ($analysisComplete && count($photos) > 0)
                        @php
                            // Somme TTC individuelle — évite la perte de centime due à l'arrondi TVA cumulée
                            $baseTtc   = (int) array_sum(array_column($analysisResults, 'price_ttc_cents'));
                            $baseHt    = (int) array_sum(array_column($analysisResults, 'price_cents'));
                            $discount  = $couponResult['discount_cents'] ?? 0;
                            // Remise appliquée sur HT, TVA recalculée sur le net HT
                            $netHt     = max(0, $baseHt - $discount);
                            $tva       = $baseTtc - $baseHt;  // TVA exacte = somme des TVA individuelles
                            $ttc       = max(0, $baseTtc - $discount);
                        @endphp
                        @if ($discount > 0)
                        <div class="flex justify-between text-sm">
                            <span class="text-[#7A6E5E]">Sous-total HT</span>
                            <span class="text-[#F5F0E8]">{{ number_format($baseHt / 100, 2, ',', ' ') }} €</span>
                        </div>
                        <div class="flex justify-between text-sm text-emerald-400">
                            <span>Réduction</span>
                            <span>−{{ number_format($discount / 100, 2, ',', ' ') }} €</span>
                        </div>
                        @endif
                        <div class="border-t border-[#C9A84C]/10 pt-3 space-y-1.5">
                            <div class="flex justify-between text-sm">
                                <span class="text-[#7A6E5E]">Total HT</span>
                                <span class="text-[#F5F0E8]">{{ number_format($netHt / 100, 2, ',', ' ') }} &euro;</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-[#7A6E5E]">TVA 20%</span>
                                <span class="text-[#F5F0E8]">{{ number_format($tva / 100, 2, ',', ' ') }} &euro;</span>
                            </div>
                            <div class="flex justify-between pt-1 border-t border-[#C9A84C]/10">
                                <span class="text-[#C9A84C] font-semibold text-sm">Total TTC</span>
                                <span class="text-[#C9A84C] font-bold text-lg">{{ number_format($ttc / 100, 2, ',', ' ') }} &euro;</span>
                            </div>
                        </div>
                        @else
                        <div class="border-t border-[#C9A84C]/10 pt-3 flex justify-between">
                            <span class="text-[#7A6E5E]">Total estimé</span>
                            <span class="text-[#C9A84C] font-bold text-lg">—</span>
                        </div>
                        @endif
                    </div>

                    <div class="mt-5 bg-[#C9A84C]/8 border border-[#C9A84C]/20 rounded-sm p-3">
                        <p class="text-[#C9A84C] text-xs leading-relaxed">
                            <strong>Aperçu d'abord, paiement ensuite.</strong><br>
                            Vous validez le résultat avant d'être débité.
                        </p>
                    </div>
                </div>

                {{-- Bloc coupon --}}
                @if ($analysisComplete)
                <div class="card-glass p-5">
                    <h3 class="text-[#F5F0E8] font-semibold text-sm mb-3">Code de réduction</h3>

                    {{-- Suggestions de bons de fidélité disponibles --}}
                    @php
                        $myCoupons = $this->getAvailableCoupons();
                    @endphp
                    @if ($myCoupons->isNotEmpty() && !($couponResult && $couponResult['valid']))
                    <div class="mb-4 space-y-2">
                        <p class="text-xs md:text-sm text-[#7A6E5E] font-medium tracking-wide uppercase">Vos bons disponibles :</p>
                        @foreach ($myCoupons as $item)
                        <button type="button" wire:click="selectLoyaltyCoupon('{{ $item->code }}')"
                                class="w-full text-left bg-[#C9A84C]/5 border border-[#C9A84C]/20 hover:border-[#C9A84C]/50 px-3 py-2 rounded-sm flex items-center justify-between text-xs md:text-sm transition-all group">
                            <span class="text-[#C9A84C] font-semibold font-serif">🎁 Bon Privilège −50%</span>
                            <span class="px-2 py-0.5 bg-[#C9A84C]/15 text-[#C9A84C] font-mono font-bold rounded-sm text-[11px] md:text-xs group-hover:bg-[#C9A84C] group-hover:text-[#0D0B08] transition-colors">
                                Appliquer
                            </span>
                        </button>
                        @endforeach
                    </div>
                    @endif

                    @if ($couponResult && $couponResult['valid'])
                    {{-- Coupon validé --}}
                    <div class="flex items-center justify-between p-3 bg-emerald-900/20 border border-emerald-500/30 rounded-sm">
                        <div>
                            <p class="text-emerald-400 text-sm font-semibold">{{ strtoupper($couponCode) }}</p>
                            <p class="text-emerald-400/70 text-xs">{{ $couponResult['message'] }}</p>
                        </div>
                        <button type="button" wire:click="removeCoupon"
                                class="text-[#7A6E5E] hover:text-red-400 transition-colors ml-3">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    @elseif ($couponResult && !$couponResult['valid'])
                    {{-- Coupon invalide --}}
                    <div class="mb-2 p-2 bg-red-900/20 border border-red-500/30 rounded-sm">
                        <p class="text-red-400 text-xs">{{ $couponResult['message'] }}</p>
                    </div>
                    @endif

                    @if (!($couponResult && $couponResult['valid']))
                    <div class="flex gap-2">
                        <input wire:model="couponCode"
                               type="text"
                               placeholder="BIENVENUE10"
                               class="flex-1 bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm
                                      px-3 py-2 placeholder-[#7A6E5E]/50 uppercase tracking-widest
                                      focus:outline-none focus:border-[#C9A84C]/60 transition-all">
                        <button type="button" wire:click="applyCoupon"
                                wire:loading.attr="disabled" wire:target="applyCoupon"
                                class="px-4 py-2 text-xs bg-[#C9A84C]/15 text-[#C9A84C] border border-[#C9A84C]/30
                                       hover:bg-[#C9A84C]/25 rounded-sm transition-all whitespace-nowrap">
                            <span wire:loading.remove wire:target="applyCoupon">Appliquer</span>
                            <span wire:loading wire:target="applyCoupon">⧖</span>
                        </button>
                    </div>
                    @error('couponCode')
                    <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                    @endif
                </div>
                @endif

                {{-- Bouton submit --}}
                <button type="submit"
                        wire:loading.attr="disabled"
                        {{ (count($photos) === 0 || !$analysisComplete || $analyzing) ? 'disabled' : '' }}
                        class="btn-gold w-full justify-center
                               {{ (count($photos) === 0 || !$analysisComplete || $analyzing) ? 'opacity-40 cursor-not-allowed' : '' }}">
                    <span wire:loading.remove wire:target="submit">
                        <svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Envoyer pour restauration
                    </span>
                    <span wire:loading wire:target="submit" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Envoi en cours…
                    </span>
                </button>

                @if (count($photos) > 0 && !$analysisComplete && !$analyzing)
                <p class="text-[#7A6E5E] text-xs text-center">⏳ En attente de l'analyse IA pour activer le bouton</p>
                @endif

            </div>
        </div>
    </form>
</div>

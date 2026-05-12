<?php
/**
 * Client — Formulaire de création de commande avec analyse IA
 * Route: GET /client/orders/create
 *
 * Flow amélioré :
 *   1. Client sélectionne 1-N photos (jusqu'à 10)
 *   2. Après sélection → analyse IA automatique (GPT-4o Vision)
 *   3. L'IA détermine le niveau de dommage et affiche son verdict + prix
 *   4. Le client peut consulter le verdict mais PAS changer le prix
 *      (évite la fraude : choisir "standard" pour une photo très abîmée)
 *   5. Le client peut ajouter des instructions optionnelles
 *   6. Soumission → création Order (PENDING) + upload médias
 */

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

    /**
     * Déclenché automatiquement quand les photos changent (wire:model.live).
     * Lance l'analyse IA pour chaque photo uploadée.
     */
    public function updatedPhotos(): void
    {
        if (empty($this->photos)) {
            $this->reset(['analysisResults', 'analyzing', 'analysisComplete', 'damage_level']);
            return;
        }

        $this->analyzing       = true;
        $this->analysisComplete = false;
        $this->analysisResults  = [];

        $analyzer = app(PhotoDamageAnalyzer::class);
        $worstLevel = 'light';
        $levelPriority = ['light' => 0, 'medium' => 1, 'heavy' => 2];

        foreach ($this->photos as $i => $photo) {
            $result = $analyzer->analyze($photo);
            $this->analysisResults[$i] = $result;

            // Worst-case : niveau le plus élevé détermine le tarif de tout le lot
            if (($levelPriority[$result['level']] ?? 0) > ($levelPriority[$worstLevel] ?? 0)) {
                $worstLevel = $result['level'];
            }
        }

        $this->damage_level     = $worstLevel;
        $this->analyzing        = false;
        $this->analysisComplete = true;

        // Réinitialiser le coupon si les photos changent (le montant HT a pu changer)
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
            // Fallback avant analyse : prix standard minimum
            return count($this->photos) * (PhotoDamageAnalyzer::PRICES['light'] ?? 83);
        }
        // Somme des prix individuels de chaque photo
        return (int) array_sum(array_column($this->analysisResults, 'price_cents'));
    }

    /**
     * Applique un code coupon.
     * Appelé via wire:click ou x-on:submit sur le formulaire coupon.
     */
    public function applyCoupon(CouponService $couponService): void
    {
        $this->validate(['couponCode' => 'required|string|min:3|max:32']);
        $this->couponResult = $couponService->apply(
            $this->couponCode,
            $this->baseHtCents()
        );
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
     * Valide et soumet la commande.
     */
    public function submit(AuditService $audit, CouponService $couponService): void
    {
        $this->validate([
            'photos'       => ['required', 'array', 'min:1', 'max:10'],
            'photos.*'     => ['required', 'file', 'mimes:jpg,jpeg,png,tiff,tif', 'max:20480'],
            'instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        // Le damage_level est déterminé par l'IA — non modifiable par le client
        $baseHtCents   = $this->baseHtCents();
        $discountCents = 0;
        $couponCode    = null;

        // Appliquer le coupon si valide
        if ($this->couponResult && $this->couponResult['valid']) {
            $discountCents = $this->couponResult['discount_cents'];
            $couponCode    = strtoupper(trim($this->couponCode));
        }

        $finalHtCents = max(0, $baseHtCents - $discountCents);

        $order = Order::create([
            'user_id'           => auth()->id(),
            'status'            => 'PENDING',
            'photo_count'       => count($this->photos),
            'damage_level'      => $this->damage_level,
            'instructions'      => $this->instructions,
            'base_price_cents'  => $baseHtCents,         // Prix HT brut (avant remise)
            'total_price_cents' => $finalHtCents,         // Prix HT net (après remise coupon)
            'coupon_code'       => $couponCode,
            'discount_cents'    => $discountCents,
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

        foreach ($this->photos as $i => $photo) {
            $src = $photo->getRealPath();

            if (! $src || ! file_exists($src)) {
                \Illuminate\Support\Facades\Log::warning("Photo {$i} introuvable, ignorée", [
                    'order_id' => $order->id,
                    'name'     => $photo->getClientOriginalName(),
                ]);
                continue;
            }

            $ext      = $photo->getClientOriginalExtension() ?: 'jpg';
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $photo->getClientOriginalName());
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
            } catch (\Throwable $e) {
                @unlink($destPath);
                \Illuminate\Support\Facades\Log::error("Upload photo originale échoué: {$e->getMessage()}", [
                    'order_id' => $order->id,
                    'file'     => $safeName,
                ]);
            }
        }

        $audit->orderCreated($order);
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
            <p class="text-[#7A6E5E] text-sm mt-1">Déposez vos photos — notre IA analyse automatiquement l'état avant de vous afficher le prix</p>
        </div>
    </div>

    <form wire:submit="submit">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- ── Colonne principale ── --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Zone upload --}}
                <div class="card-glass p-6">
                    <h2 class="text-[#F5F0E8] font-semibold mb-1">Vos photos à restaurer</h2>
                    <p class="text-[#7A6E5E] text-sm mb-5">JPEG, PNG ou TIFF &mdash; 20 Mo max par photo &mdash; jusqu'&agrave; 10 photos</p>

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
                        <input id="photos-input" type="file" wire:model.live="photos" multiple accept=".jpg,.jpeg,.png,.tiff,.tif" class="hidden">
                    </label>

                    @error('photos.*') <p class="text-red-400 text-xs mt-2">{{ $message }}</p> @enderror

                    {{-- Loading upload --}}
                    <div wire:loading wire:target="photos" class="mt-4 flex items-center gap-2 text-[#7A6E5E] text-sm">
                        <svg class="animate-spin w-4 h-4 text-[#C9A84C]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Chargement des photos…
                    </div>

                    {{-- Grille photos + résultats IA --}}
                    @if (count($photos) > 0)
                    <div class="mt-5 space-y-3">

                        {{-- Banner analyse en cours --}}
                        @if ($analyzing)
                        <div class="flex items-center gap-3 bg-[#C9A84C]/10 border border-[#C9A84C]/25 rounded-sm px-4 py-3">
                            <svg class="animate-spin w-4 h-4 text-[#C9A84C] shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <div>
                                <p class="text-[#C9A84C] text-sm font-medium">Analyse IA en cours…</p>
                                <p class="text-[#7A6E5E] text-xs">Notre algorithme examine l'état de chaque photo pour définir le tarif exact</p>
                            </div>
                        </div>
                        @endif

                        {{-- Grille des photos --}}
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                            @foreach ($photos as $i => $photo)
                            <div class="relative group">
                                {{-- Miniature --}}
                                <div class="aspect-square bg-[#1A1510] rounded-sm overflow-hidden border border-[#C9A84C]/15">
                                    <img src="{{ $photo->temporaryUrl() }}" alt="Photo {{ $i + 1 }}" class="w-full h-full object-cover">
                                </div>

                                {{-- Badge résultat IA --}}
                                @if (isset($analysisResults[$i]))
                                @php $result = $analysisResults[$i]; @endphp
                                 @php
                                 $lvlCfg = match($result['level']) {
                                     'heavy'  => [
                                         'bg'    => 'bg-orange-950/60 border border-orange-500/30',
                                         'text'  => 'text-orange-400',
                                         'bar'   => 'bg-orange-400',
                                         'label' => '&#9888; Compl&egrave;te &middot; 3&euro;',
                                     ],
                                     'medium' => [
                                         'bg'    => 'bg-amber-950/60 border border-amber-500/30',
                                         'text'  => 'text-amber-400',
                                         'bar'   => 'bg-amber-400',
                                         'label' => '~ Avanc&eacute;e &middot; 2&euro;',
                                     ],
                                     default  => [
                                         'bg'    => 'bg-emerald-950/60 border border-emerald-500/30',
                                         'text'  => 'text-emerald-400',
                                         'bar'   => 'bg-emerald-400',
                                         'label' => '&#10003; Standard &middot; 1&euro;',
                                     ],
                                 };
                                 @endphp
                                 <div class="mt-1.5 px-2 py-1 rounded-sm text-center {{ $lvlCfg['bg'] }}">
                                     <p class="text-xs font-semibold {{ $lvlCfg['text'] }}">{!! $lvlCfg['label'] !!}</p>
                                    <p class="text-[10px] text-[#7A6E5E] mt-0.5 leading-tight">{{ $result['reason'] }}</p>
                                    {{-- Barre de confiance --}}
                                    <div class="mt-1 h-1 bg-[#1A1510] rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-500 {{ $lvlCfg['bar'] }}"
                                             style="width: {{ $result['confidence'] }}%">
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-[#7A6E5E] mt-0.5">Confiance : {{ $result['confidence'] }}%
                                        @if(!$result['ai_used']) <span class="text-yellow-500">⚡ heuristique</span> @endif
                                    </p>
                                </div>
                                @elseif ($analyzing)
                                <div class="mt-1.5 px-2 py-1 bg-[#1A1510] rounded-sm border border-[#C9A84C]/15 text-center">
                                    <svg class="animate-spin w-3 h-3 text-[#C9A84C] mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                </div>
                                @endif
                            </div>
                            @endforeach
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
                            <span>Le tarif est d&eacute;fini automatiquement par analyse IA pour garantir l'&eacute;quit&eacute;. Notre &eacute;quipe peut r&eacute;viser le prix apr&egrave;s examen manuel si vous contestez le verdict.</span>
                        </div>
                        @endif
                    </div>
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
                                            $priceSub = 'd&eacute;fini par IA';
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
                            // Somme individuelle — chaque photo à son propre tarif IA
                            $baseHt    = (int) array_sum(array_column($analysisResults, 'price_cents'));
                            $discount  = $couponResult['discount_cents'] ?? 0;
                            $netHt     = max(0, $baseHt - $discount);
                            $tva       = (int) round($netHt * 0.2);
                            $ttc       = $netHt + $tva;
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
                                <span class="text-[#F5F0E8]">{{ number_format($netHt / 100, 2, ',', ' ') }} €</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-[#7A6E5E]">TVA 20%</span>
                                <span class="text-[#7A6E5E]">{{ number_format($tva / 100, 2, ',', ' ') }} €</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[#7A6E5E]">Total TTC</span>
                                <span class="text-[#C9A84C] font-bold text-lg">{{ number_format($ttc / 100, 2, ',', ' ') }} €</span>
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

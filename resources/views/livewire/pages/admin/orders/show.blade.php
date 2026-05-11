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
        $this->order      = $order->load(['user', 'media', 'delivery', 'auditLogs']);
        $this->finalPrice = $this->order->total_price_cents
            ? number_format($this->order->total_price_cents / 100, 2, '.', '')
            : number_format(($this->order->base_price_cents ?? 0) / 100, 2, '.', '');
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

    /** Upload des photos restaurées + transition IN_PROGRESS → DONE */
    public function uploadAndMarkDone(AuditService $audit): void
    {
        $this->validate([
            'restoredPhotos'   => ['required', 'array', 'min:1'],
            'restoredPhotos.*' => ['required', 'file', 'mimes:jpg,jpeg,png,tiff,tif,webp', 'max:51200'],
            'finalPrice'       => ['required', 'numeric', 'min:0.5'],
        ]);

        // Fixer le prix final et les notes
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

        // Passer les copies stables à Spatie MediaLibrary
        $uploaded = 0;
        foreach ($tmpCopies as $tmp) {
            try {
                $this->order
                    ->addMedia($tmp['path'])          // path stable, pas encore supprimé
                    ->usingFileName($tmp['name'])
                    ->withCustomProperties(['uploaded_by_admin' => true])
                    ->preservingOriginal()            // ne pas supprimer le fichier source
                    ->toMediaCollection('retouched');

                unlink($tmp['path']); // nettoyage manuel après succès
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

        // Transition de statut → déclenche OrderObserver → email client
        $previous = $this->order->status;
        $this->order->markAsDone();
        $audit->orderStatusChanged($this->order, $previous, 'DONE');

        $this->restoredPhotos = [];
        session()->flash('success', "{$uploaded} photo(s) restaurée(s) uploadée(s). Client notifié par email.");
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
        session()->flash('success', 'Notes et prix sauvegardés.');
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
}; ?>

<div>

    <div class="flex items-center gap-4 mb-8">
        <a href="{{ route('admin.orders.index') }}" wire:navigate class="text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div class="flex-1">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-[#F5F0E8]">Commande</h1>
                <span class="font-mono text-[#C9A84C]">{{ $order->reference }}</span>
                @php
                    $badges = ['PENDING'=>'bg-yellow-900/40 text-yellow-400 border-yellow-500/30','IN_PROGRESS'=>'bg-blue-900/40 text-blue-400 border-blue-500/30','DONE'=>'bg-[#C9A84C]/15 text-[#C9A84C] border-[#C9A84C]/30','PAID'=>'bg-emerald-900/40 text-emerald-400 border-emerald-500/30','CANCELLED'=>'bg-red-900/30 text-red-400 border-red-500/30'];
                    $labels = ['PENDING'=>'En attente','IN_PROGRESS'=>'En cours','DONE'=>'Aperçu prêt','PAID'=>'Payé ✓','CANCELLED'=>'Annulé'];
                @endphp
                <span class="px-2.5 py-1 text-xs border rounded-full {{ $badges[$order->status] ?? '' }}">
                    {{ $labels[$order->status] ?? $order->status }}
                </span>
            </div>
            <p class="text-[#7A6E5E] text-sm mt-1">
                {{ $order->user->name }} · {{ $order->user->email }} · {{ $order->created_at->format('d/m/Y H:i') }}
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
                        <div class="group relative aspect-square bg-[#1A1510] rounded-sm overflow-hidden border border-[#C9A84C]/10">
                            <img src="{{ $media->getUrl() }}" alt="{{ $media->file_name }}" class="w-full h-full object-cover">
                            <a href="{{ $media->getUrl() }}" target="_blank"
                               class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </a>
                            {{-- Propriétés IA si disponibles --}}
                            @php $aiLevel = $media->getCustomProperty('ai_level'); @endphp
                            @if ($aiLevel)
                            <div class="absolute top-1 left-1">
                                <span class="text-[9px] px-1.5 py-0.5 rounded-full font-bold {{ $aiLevel === 'heavy' ? 'bg-orange-500 text-black' : 'bg-emerald-500 text-black' }}">
                                    {{ $aiLevel === 'heavy' ? '⚠' : '✓' }} IA
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
            {{-- Disponible pour PENDING et IN_PROGRESS --}}
            @if (in_array($order->status, ['PENDING', 'IN_PROGRESS']))
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

            {{-- === ACTION : UPLOAD + MARQUER DONE === --}}
            @if ($order->status === 'IN_PROGRESS')
            <form wire:submit="uploadAndMarkDone" class="card-glass p-6 border-blue-500/20">
                <h2 class="text-[#F5F0E8] font-semibold mb-1">Uploader les photos restaurées</h2>
                <p class="text-[#7A6E5E] text-sm mb-5">Une fois uploadées, le client recevra un email avec le lien de paiement.</p>

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
                    <label class="block text-[#7A6E5E] text-xs uppercase tracking-widest mb-1.5">Prix final (€) — révision du tarif IA</label>
                    <div class="flex items-center gap-3">
                        <input wire:model="finalPrice" type="number" step="0.01" min="0.5"
                               class="w-36 bg-[#1A1510] border border-[#C9A84C]/20 text-[#C9A84C] font-bold text-lg text-center rounded-sm px-4 py-2 focus:outline-none focus:border-[#C9A84C]/60 transition-all">
                        <span class="text-[#7A6E5E] text-sm">€ TTC
                            <span class="ml-2 text-xs">(IA suggérait : {{ number_format(($order->base_price_cents ?? 0) / 100, 2, ',', ' ') }} €)</span>
                        </span>
                    </div>
                    @error('finalPrice') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled"
                        class="w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-sm transition-all hover:shadow-[0_0_20px_rgba(59,130,246,0.3)]">
                    <span wire:loading.remove wire:target="uploadAndMarkDone">
                        ✓ Uploader et notifier le client
                    </span>
                    <span wire:loading wire:target="uploadAndMarkDone" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Upload en cours…
                    </span>
                </button>
            </form>
            @endif

            {{-- === PHOTOS RESTAURÉES UPLOADÉES === --}}
            @if ($order->getMedia('retouched')->isNotEmpty())
            <div class="card-glass p-5">
                <h2 class="text-[#F5F0E8] font-semibold mb-4">Photos restaurées uploadées</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                    @foreach ($order->getMedia('retouched') as $media)
                    <div class="relative group aspect-square bg-[#1A1510] rounded-sm overflow-hidden border border-emerald-500/20">
                        <img src="{{ $media->getUrl() }}" class="w-full h-full object-cover">
                        <a href="{{ $media->getUrl() }}" target="_blank"
                           class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </a>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>

        {{-- ── Sidebar admin ── --}}
        <div class="space-y-5">

            {{-- Détails --}}
            <div class="card-glass p-5">
                <h3 class="text-[#7A6E5E] text-xs tracking-widest uppercase mb-4">Détails commande</h3>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between"><dt class="text-[#7A6E5E]">Photos</dt><dd class="text-[#F5F0E8]">{{ $order->photo_count }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[#7A6E5E]">Niveau IA</dt>
                        <dd class="{{ $order->damage_level === 'heavy' ? 'text-orange-400' : 'text-emerald-400' }} font-medium">
                            {{ $order->damage_level === 'heavy' ? 'Avancée (10€)' : 'Standard (1€)' }}
                        </dd>
                    </div>
                    <div class="flex justify-between"><dt class="text-[#7A6E5E]">Estimation IA</dt><dd class="text-[#C9A84C]">{{ number_format(($order->base_price_cents ?? 0) / 100, 2, ',', ' ') }} €</dd></div>
                    @if ($order->total_price_cents)
                    <div class="flex justify-between pt-2 border-t border-[#C9A84C]/10"><dt class="text-[#7A6E5E]">Prix final</dt><dd class="text-[#C9A84C] font-bold">{{ number_format($order->total_price_cents / 100, 2, ',', ' ') }} €</dd></div>
                    @endif
                    @if ($order->paid_at)
                    <div class="flex justify-between"><dt class="text-[#7A6E5E]">Payé le</dt><dd class="text-emerald-400 text-xs">{{ $order->paid_at->format('d/m/Y H:i') }}</dd></div>
                    @endif
                </dl>
            </div>

            {{-- Notes + prix rapide --}}
            <div class="card-glass p-5">
                <h3 class="text-[#7A6E5E] text-xs tracking-widest uppercase mb-4">Notes internes</h3>
                <textarea wire:model="adminNotes" rows="4" placeholder="Notes visibles uniquement par l'équipe…"
                          class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-xs rounded-sm px-3 py-2 placeholder-[#7A6E5E]/50 resize-none focus:outline-none focus:border-[#C9A84C]/60 transition-all mb-3">
                </textarea>
                @if (!in_array($order->status, ['PAID', 'DELIVERED', 'CANCELLED']))
                <div class="flex gap-2 mb-3">
                    <input wire:model="finalPrice" type="number" step="0.01" placeholder="Prix €"
                           class="flex-1 bg-[#1A1510] border border-[#C9A84C]/20 text-[#C9A84C] text-sm rounded-sm px-3 py-2 focus:outline-none focus:border-[#C9A84C]/60 transition-all">
                    <button wire:click="saveNotes" class="px-4 py-2 text-xs bg-[#C9A84C]/20 text-[#C9A84C] border border-[#C9A84C]/30 hover:bg-[#C9A84C]/30 rounded-sm transition-all">
                        Sauver
                    </button>
                </div>
                @endif
            </div>

            {{-- Annulation --}}
            @if (in_array($order->status, ['PENDING', 'IN_PROGRESS']))
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

            {{-- Audit log --}}
            @if ($order->auditLogs->isNotEmpty())
            <div class="card-glass p-5">
                <h3 class="text-[#7A6E5E] text-xs tracking-widest uppercase mb-3">Historique</h3>
                <div class="space-y-2.5">
                    @foreach ($order->auditLogs->sortByDesc('created_at')->take(8) as $log)
                    <div class="flex items-start gap-2 text-xs">
                        <div class="w-1.5 h-1.5 rounded-full bg-[#C9A84C]/40 mt-1.5 shrink-0"></div>
                        <div>
                            <p class="text-[#F5F0E8]">{{ $log->action }}</p>
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

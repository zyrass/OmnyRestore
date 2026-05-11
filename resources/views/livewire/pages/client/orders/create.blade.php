<?php
/**
 * Client — Formulaire de création de commande
 * Route: GET /client/orders/create
 * Middleware: auth, verified
 *
 * Permet au client de soumettre ses photos pour restauration.
 * Les photos sont uploadées via Livewire (withFileUploads) vers le stockage temporaire,
 * puis déplacées vers S3 après validation.
 *
 * Flow:
 *   1. Client sélectionne 1-10 photos (JPEG, PNG, TIFF, max 20MB chacune)
 *   2. Client précise le type de dommage (détermine le tarif estimé)
 *   3. Client ajoute des instructions optionnelles
 *   4. Soumission → création Order (PENDING) + upload médias → redirection vers show
 */

use App\Models\Order;
use App\Services\AuditService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new
#[Layout('layouts.app')]
#[Title('Nouvelle commande')]
class extends Component
{
    use WithFileUploads;

    #[Validate(['photos.*' => 'required|file|mimes:jpg,jpeg,png,tiff,tif|max:20480'])]
    public array $photos = [];

    #[Validate('required|in:light,heavy')]
    public string $damage_level = 'light';

    #[Validate('nullable|string|max:1000')]
    public ?string $instructions = null;

    /**
     * Soumet la commande.
     * Crée l'enregistrement Order, attache les médias via Spatie Media Library,
     * puis redirige vers la page de détail.
     */
    public function submit(AuditService $audit): void
    {
        $this->validate();

        // Créer la commande
        $order = Order::create([
            'user_id'           => auth()->id(),
            'status'            => 'PENDING',
            'photo_count'       => count($this->photos),
            'damage_level'      => $this->damage_level,
            'instructions'      => $this->instructions,
            // Prix estimé (definitif fixé par l'admin)
            'base_price_cents'  => $this->damage_level === 'light'
                ? count($this->photos) * 100   // 1€/photo
                : count($this->photos) * 1000, // 10€/photo
        ]);

        // Attacher les photos originales via Spatie Media Library
        foreach ($this->photos as $photo) {
            $order->addMedia($photo->getRealPath())
                  ->usingFileName($photo->getClientOriginalName())
                  ->toMediaCollection('originals');
        }

        // Log d'audit
        $audit->orderCreated($order);

        // Rediriger vers la page de détail
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
            <p class="text-[#7A6E5E] text-sm mt-1">Déposez vos photos — vous verrez l'aperçu avant de payer</p>
        </div>
    </div>

    <form wire:submit="submit">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- ── Colonne principale ── --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Upload zone --}}
                <div class="card-glass p-6">
                    <h2 class="text-[#F5F0E8] font-semibold mb-1">Vos photos</h2>
                    <p class="text-[#7A6E5E] text-xs mb-5">JPEG, PNG ou TIFF — 20 Mo max par photo — jusqu'à 10 photos</p>

                    {{-- Zone de dépôt --}}
                    <label for="photos-input"
                           class="block border-2 border-dashed border-[#C9A84C]/20 hover:border-[#C9A84C]/50
                                  rounded-sm p-10 text-center cursor-pointer transition-all duration-300
                                  hover:bg-[#C9A84C]/3"
                           x-data="{ dragging: false }"
                           @dragover.prevent="dragging = true"
                           @dragleave="dragging = false"
                           @drop.prevent="dragging = false"
                           :class="dragging ? 'border-[#C9A84C]/70 bg-[#C9A84C]/5' : ''">

                        <svg class="w-12 h-12 text-[#C9A84C]/30 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="text-[#F5F0E8] text-sm font-medium mb-1">Glissez vos photos ici</p>
                        <p class="text-[#7A6E5E] text-xs">ou <span class="text-[#C9A84C] underline">cliquez pour sélectionner</span></p>

                        <input id="photos-input" type="file" wire:model="photos" multiple accept=".jpg,.jpeg,.png,.tiff,.tif" class="hidden">
                    </label>

                    {{-- Erreurs upload --}}
                    @error('photos.*') <p class="text-red-400 text-xs mt-2">{{ $message }}</p> @enderror

                    {{-- Aperçu des photos sélectionnées --}}
                    @if (count($photos) > 0)
                    <div class="mt-4 grid grid-cols-3 sm:grid-cols-4 gap-3">
                        @foreach ($photos as $i => $photo)
                        <div class="relative group aspect-square bg-[#1A1510] rounded-sm overflow-hidden border border-[#C9A84C]/15">
                            <img src="{{ $photo->temporaryUrl() }}" alt="Photo {{ $i + 1 }}"
                                 class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <span class="text-white text-xs">{{ round($photo->getSize() / 1024 / 1024, 1) }} Mo</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <p class="text-[#C9A84C] text-xs mt-3">{{ count($photos) }} photo{{ count($photos) > 1 ? 's' : '' }} sélectionnée{{ count($photos) > 1 ? 's' : '' }}</p>
                    @endif

                    {{-- Loading indicator --}}
                    <div wire:loading wire:target="photos" class="mt-3 flex items-center gap-2 text-[#7A6E5E] text-xs">
                        <svg class="animate-spin w-4 h-4 text-[#C9A84C]" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Chargement des photos…
                    </div>
                </div>

                {{-- Instructions optionnelles --}}
                <div class="card-glass p-6">
                    <h2 class="text-[#F5F0E8] font-semibold mb-1">Instructions (optionnel)</h2>
                    <p class="text-[#7A6E5E] text-xs mb-4">Précisez des détails importants : zones à préserver, personnes particulières, etc.</p>
                    <textarea wire:model="instructions" id="instructions"
                              rows="4"
                              placeholder="Ex : Photo de mariage 1967. La robe de mariée doit être blanche. Préserver les visages à gauche..."
                              class="w-full bg-[#1A1510] border border-[#C9A84C]/20 text-[#F5F0E8] text-sm rounded-sm px-4 py-3
                                     placeholder-[#7A6E5E]/50 resize-none
                                     focus:outline-none focus:border-[#C9A84C]/60 focus:ring-1 focus:ring-[#C9A84C]/30
                                     transition-all duration-200">
                    </textarea>
                    <x-input-error :messages="$errors->get('instructions')" class="mt-1.5" />
                    <p class="text-[#7A6E5E] text-xs mt-2 text-right" wire:ignore>
                        <span x-text="(document.getElementById('instructions')?.value?.length ?? 0) + '/1000'">0/1000</span>
                    </p>
                </div>

            </div>

            {{-- ── Colonne récap ── --}}
            <div class="space-y-6">

                {{-- Type de dommage --}}
                <div class="card-glass p-6">
                    <h2 class="text-[#F5F0E8] font-semibold mb-4">Type de dommage</h2>

                    <div class="space-y-3">
                        {{-- Standard --}}
                        <label class="flex items-start gap-3 p-4 rounded-sm border cursor-pointer transition-all
                                      {{ $damage_level === 'light' ? 'border-[#C9A84C]/50 bg-[#C9A84C]/8' : 'border-[#C9A84C]/15 hover:border-[#C9A84C]/30' }}">
                            <input type="radio" wire:model.live="damage_level" value="light" class="mt-0.5 text-[#C9A84C] border-[#C9A84C]/40 bg-[#0D0B08] focus:ring-[#C9A84C]/30 focus:ring-offset-[#0D0B08]">
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-[#F5F0E8] text-sm font-medium">Restauration Standard</span>
                                    <span class="text-[#C9A84C] text-sm font-bold">1 € / photo</span>
                                </div>
                                <p class="text-[#7A6E5E] text-xs mt-1">Jaunissement, poussière, légères rayures — état correct</p>
                            </div>
                        </label>

                        {{-- Avancée --}}
                        <label class="flex items-start gap-3 p-4 rounded-sm border cursor-pointer transition-all
                                      {{ $damage_level === 'heavy' ? 'border-[#C9A84C]/50 bg-[#C9A84C]/8' : 'border-[#C9A84C]/15 hover:border-[#C9A84C]/30' }}">
                            <input type="radio" wire:model.live="damage_level" value="heavy" class="mt-0.5 text-[#C9A84C] border-[#C9A84C]/40 bg-[#0D0B08] focus:ring-[#C9A84C]/30 focus:ring-offset-[#0D0B08]">
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-[#F5F0E8] text-sm font-medium">Restauration Avancée</span>
                                    <span class="text-[#C9A84C] text-sm font-bold">10 € / photo</span>
                                </div>
                                <p class="text-[#7A6E5E] text-xs mt-1">Déchirures, dommages eau, pliures — très endommagée</p>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Récapitulatif --}}
                <div class="card-glass p-6">
                    <h2 class="text-[#F5F0E8] font-semibold mb-4">Récapitulatif</h2>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-[#7A6E5E]">Photos</span>
                            <span class="text-[#F5F0E8]">{{ count($photos) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#7A6E5E]">Type</span>
                            <span class="text-[#F5F0E8]">{{ $damage_level === 'light' ? 'Standard' : 'Avancée' }}</span>
                        </div>
                        <div class="border-t border-[#C9A84C]/10 pt-3 flex justify-between">
                            <span class="text-[#7A6E5E]">Estimation HT</span>
                            <span class="text-[#C9A84C] font-bold">
                                {{ count($photos) > 0
                                    ? number_format(count($photos) * ($damage_level === 'light' ? 1 : 10), 0, ',', '') . ' €'
                                    : '—' }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 bg-[#C9A84C]/8 border border-[#C9A84C]/20 rounded-sm p-3">
                        <p class="text-[#C9A84C] text-xs leading-relaxed">
                            <strong>Aperçu d'abord, paiement ensuite.</strong><br>
                            Vous ne serez débité qu'après avoir validé l'aperçu.
                        </p>
                    </div>
                </div>

                {{-- Bouton de soumission --}}
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="btn-gold w-full justify-center {{ count($photos) === 0 ? 'opacity-50 cursor-not-allowed' : '' }}"
                        {{ count($photos) === 0 ? 'disabled' : '' }}>
                    <span wire:loading.remove wire:target="submit">
                        <svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Envoyer pour restauration
                    </span>
                    <span wire:loading wire:target="submit" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Envoi en cours…
                    </span>
                </button>

            </div>
        </div>
    </form>
</div>

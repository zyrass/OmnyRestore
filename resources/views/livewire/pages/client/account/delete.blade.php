<?php
/**
 * Client — Page de suppression de compte (RGPD Art. 17)
 * Route: GET /client/account/delete
 * Middleware: auth, verified
 *
 * Flux :
 *   1. Affichage de la page avec résumé de ce qui sera supprimé / conservé
 *   2. Saisie du mot de passe + case à cocher de confirmation
 *   3. Soumission → DeleteUserAction::execute()
 *   4. Logout + redirect vers / avec message de confirmation
 */

use App\Actions\DeleteUserAction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Supprimer mon compte')]
class extends Component
{
    #[Validate('required|string|min:1')]
    public string $password = '';

    #[Validate('accepted')]
    public bool $confirmed = false;

    public bool $showForm = false;

    /** Affiche le formulaire de confirmation */
    public function revealForm(): void
    {
        $this->showForm = true;
    }

    /**
     * Déclenche l'anonymisation RGPD puis déconnecte le client.
     * Toute erreur de mot de passe est renvoyée en inline error.
     */
    public function deleteAccount(): void
    {
        $this->validate();

        $user = Auth::user();

        try {
            (new DeleteUserAction())->execute($user, $this->password);
        } catch (\InvalidArgumentException $e) {
            $this->addError('password', $e->getMessage());
            return;
        } catch (\LogicException $e) {
            session()->flash('error', $e->getMessage());
            return;
        }

        // Invalide la session AVANT la redirection (le user est soft-deleted)
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        redirect('/')->with(
            'success',
            '✅ Votre compte a été supprimé. Vos données personnelles ont été anonymisées. Merci d\'avoir utilisé OmnyRestore.'
        );
    }
}; ?>

<div>
    {{-- En-tête --}}
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
            <a href="{{ route('client.profile') }}" class="text-[#7A6E5E] hover:text-[#C9A84C] transition-colors text-sm">
                ← Mon profil
            </a>
        </div>
        <h1 class="text-2xl font-bold text-red-400">Supprimer mon compte</h1>
        <p class="text-[#7A6E5E] text-sm mt-1">Cette action est <strong class="text-red-400">irréversible</strong>. Lisez attentivement les informations ci-dessous.</p>
    </div>

    <div class="w-full space-y-6">

        {{-- Flash messages --}}
        @if (session('error'))
        <div class="card-glass p-4 border border-red-500/30 bg-red-500/5">
            <p class="text-red-400 text-sm">{{ session('error') }}</p>
        </div>
        @endif

        {{-- Récapitulatif : ce qui sera supprimé --}}
        <div class="card-glass p-6 border border-red-500/20">
            <h2 class="text-red-400 font-semibold mb-4 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Ce qui sera <span class="underline">immédiatement</span> supprimé
            </h2>
            <ul class="space-y-2 text-sm text-[#9E9085]">
                @foreach ([
                    'Toutes vos photos originales soumises pour restauration',
                    'Toutes vos photos restaurées (aperçus + haute résolution)',
                    'Votre adresse email, nom et mot de passe',
                    'Votre identifiant Stripe (méthodes de paiement)',
                    'Vos tickets de support et messages',
                    'Votre préférence marketing et consentement email',
                ] as $item)
                <li class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-red-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    {{ $item }}
                </li>
                @endforeach
            </ul>
        </div>

        {{-- Récapitulatif : ce qui est conservé --}}
        <div class="card-glass p-6 border border-[#C9A84C]/20">
            <h2 class="text-[#C9A84C] font-semibold mb-4 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Ce qui est conservé <span class="text-[#7A6E5E] font-normal">(obligations légales)</span>
            </h2>
            <ul class="space-y-3 text-sm text-[#9E9085]">
                <li class="flex items-start gap-3">
                    <svg class="w-4 h-4 text-[#C9A84C] mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>
                        Données de commandes et <strong class="text-[#F5F0E8]">factures anonymisées</strong> (conservées 10 ans selon l'Art. L.123-22 du Code de commerce).
                    </span>
                </li>
                <li class="flex items-start gap-3">
                    <svg class="w-4 h-4 text-[#C9A84C] mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>
                        Horodatage de votre <strong class="text-[#F5F0E8]">consentement RGPD</strong> initial (Art. 7.1 RGPD).
                    </span>
                </li>
                <li class="flex items-start gap-3 italic text-[#7A6E5E] text-xs pl-7">
                    Ces données sont dissociées de votre identité — elles ne permettent plus de vous identifier.
                </li>
            </ul>
        </div>

        {{-- Bouton "Afficher le formulaire" ou formulaire --}}
        @if (! $showForm)
        <div class="text-center">
            <button wire:click="revealForm"
                    class="px-8 py-3 border border-red-500/40 text-red-400 hover:bg-red-500/10 hover:border-red-500/70
                           text-sm font-medium tracking-wider uppercase transition-all duration-200 rounded-sm">
                Je comprends — Continuer vers la suppression
            </button>
        </div>

        @else

        {{-- Formulaire de confirmation --}}
        <div class="card-glass p-6 border border-red-500/30">
            <h2 class="text-[#F5F0E8] font-semibold mb-5">Confirmer la suppression</h2>

            <div class="space-y-5">
                {{-- Mot de passe --}}
                <div>
                    <label for="password" class="block text-sm text-[#9E9085] mb-2">
                        Confirmez votre mot de passe
                    </label>
                    <input
                        wire:model="password"
                        type="password"
                        id="delete-password"
                        placeholder="Votre mot de passe actuel"
                        autocomplete="current-password"
                        class="w-full bg-[#0F0C08] border border-[#3A3028] text-[#F5F0E8] rounded-sm px-4 py-3
                               text-sm placeholder-[#4A3E2E] focus:outline-none focus:border-red-500/60 transition-colors"
                    >
                    @error('password')
                    <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Case à cocher --}}
                <div class="flex items-start gap-3">
                    <input
                        wire:model="confirmed"
                        type="checkbox"
                        id="delete-confirmed"
                        class="mt-1 w-4 h-4 accent-red-500 shrink-0"
                    >
                    <label for="delete-confirmed" class="text-sm text-[#9E9085] leading-relaxed cursor-pointer">
                        Je comprends que cette action est <strong class="text-red-400">irréversible</strong>.
                        Mes photos seront <strong class="text-[#F5F0E8]">définitivement supprimées</strong>
                        et mes données personnelles seront anonymisées immédiatement.
                    </label>
                </div>
                @error('confirmed')
                <p class="text-red-400 text-xs">{{ $message }}</p>
                @enderror

                {{-- Bouton final --}}
                <div class="pt-2">
                    <button
                        @click="
                            const wire = $wire;
                            if (!wire.confirmed) {
                                omnyConfirm({
                                    title: 'Action requise',
                                    message: 'Vous devez obligatoirement cocher la case de confirmation pour pouvoir supprimer votre compte.',
                                    confirmLabel: 'Compris',
                                    danger: false
                                });
                                return;
                            }
                            omnyConfirm({
                                title: 'Suppression Définitive',
                                message: 'Êtes-vous absolument certain ? Votre compte sera anonymisé et TOUTES vos photos seront supprimées sans aucun recours possible.',
                                confirmLabel: 'Oui, supprimer définitivement',
                                danger: true
                            }).then(() => wire.deleteAccount())
                        "
                        wire:loading.attr="disabled"
                        class="w-full py-3 bg-red-600/20 border border-red-500/50 text-red-400
                               hover:bg-red-600/30 hover:border-red-400 font-semibold tracking-wider
                               uppercase text-sm transition-all duration-200 rounded-sm flex items-center justify-center gap-2"
                    >
                        <svg wire:loading wire:target="deleteAccount" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <svg wire:loading.remove wire:target="deleteAccount" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Supprimer définitivement mon compte
                    </button>
                </div>

                <p class="text-[#4A3E2E] text-xs text-center">
                    En cliquant sur ce bouton, vous confirmez avoir lu et compris les conséquences de cette suppression.
                </p>
            </div>
        </div>
        @endif

    </div>
</div>

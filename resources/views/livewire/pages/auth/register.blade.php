<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $rgpd_consent = false;

    public function register(): void
    {
        $validated = $this->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password'              => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'rgpd_consent'          => ['accepted'],
        ], [
            'rgpd_consent.accepted' => 'Vous devez accepter la politique de confidentialité pour créer un compte.',
        ]);

        $validated['password']      = Hash::make($validated['password']);
        $validated['rgpd_consent_at'] = now();
        unset($validated['rgpd_consent']);

        event(new Registered($user = User::create($validated)));

        $this->redirect(route('registration.success', absolute: false), navigate: true);
    }
}; ?>

<div>
    {{-- Page title --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-[#F5F0E8] mb-1">Créer un compte</h1>
        <p class="text-[#7A6E5E] text-sm">Déposez vos premières photos à restaurer</p>
    </div>

    <form wire:submit="register" class="space-y-5">

        {{-- Nom --}}
        <div>
            <x-input-label for="name" :value="__('Prénom et nom')" />
            <x-text-input
                wire:model="name" id="name" type="text" name="name"
                placeholder="Marie Dupont"
                required autofocus autocomplete="name"
            />
            <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
        </div>

        {{-- Email --}}
        <div>
            <x-input-label for="email" :value="__('Adresse email')" />
            <x-text-input
                wire:model="email" id="email" type="email" name="email"
                placeholder="vous@exemple.fr"
                required autocomplete="username"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        {{-- Password --}}
        <div x-data="{ show: false }">
            <x-input-label for="password" :value="__('Mot de passe')" />
            <div class="relative">
                <x-text-input
                    wire:model="password" id="password" x-bind:type="show ? 'text' : 'password'" name="password"
                    placeholder="12 car. min. · Maj · Chiffre · Symbole"
                    required autocomplete="new-password"
                    class="pr-12"
                />
                <button type="button" @click="show = !show"
                        class="absolute right-0 top-0 bottom-0 px-4 flex items-center text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
                    <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.076m1.089-3.34A10.001 10.001 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.059 10.059 0 01-2.015 3.123m-4.656-1.123a3 3 0 11-4.243-4.242M10.477 10.477L13.523 13.523M2 2l20 20"/></svg>
                </button>
            </div>
            <p class="text-[#7A6E5E] text-xs mt-1.5">
                Recommandation CNIL : 12 caractères minimum avec majuscule, chiffre et symbole (ex: <span class="text-[#C9A84C]/70">MonPhoto2024!</span>)
            </p>
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        {{-- Confirm Password --}}
        <div x-data="{ show: false }">
            <x-input-label for="password_confirmation" :value="__('Confirmer le mot de passe')" />
            <div class="relative">
                <x-text-input
                    wire:model="password_confirmation" id="password_confirmation" x-bind:type="show ? 'text' : 'password'"
                    name="password_confirmation"
                    placeholder="Répéter le mot de passe"
                    required autocomplete="new-password"
                    class="pr-12"
                />
                <button type="button" @click="show = !show"
                        class="absolute right-0 top-0 bottom-0 px-4 flex items-center text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
                    <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.076m1.089-3.34A10.001 10.001 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.059 10.059 0 01-2.015 3.123m-4.656-1.123a3 3 0 11-4.243-4.242M10.477 10.477L13.523 13.523M2 2l20 20"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5" />
        </div>

        {{-- RGPD Consent --}}
        <div class="bg-[#1A1510] border border-[#C9A84C]/15 rounded-sm p-4">
            <label for="rgpd_consent" class="flex items-start gap-3 cursor-pointer">
                <input wire:model="rgpd_consent" id="rgpd_consent" type="checkbox" name="rgpd_consent"
                       class="mt-0.5 w-4 h-4 rounded-sm border border-[#C9A84C]/30 bg-[#0D0B08] text-[#C9A84C]
                              checked:bg-[#C9A84C] focus:ring-[#C9A84C]/30 focus:ring-offset-[#0D0B08] shrink-0">
                <span class="text-[#7A6E5E] text-xs leading-relaxed">
                    J'accepte la
                    <a href="{{ route('legal.privacy') }}" wire:navigate target="_blank" class="text-[#C9A84C] hover:text-[#E8C97A] transition-colors underline">politique de confidentialité</a>
                    et les
                    <a href="{{ route('legal.cgv') }}" wire:navigate target="_blank" class="text-[#C9A84C] hover:text-[#E8C97A] transition-colors underline">conditions générales de vente</a>.
                    Mes photos seront supprimées 6 mois après livraison.
                </span>
            </label>
            <x-input-error :messages="$errors->get('rgpd_consent')" class="mt-2" />
        </div>

        {{-- Submit --}}
        <div class="pt-1">
            <x-primary-button>
                Créer mon compte
            </x-primary-button>
        </div>

    </form>

    {{-- Login link --}}
    <p class="mt-6 text-center text-sm text-[#7A6E5E]">
        Déjà un compte ?
        <a href="{{ route('login') }}" wire:navigate class="text-[#C9A84C] hover:text-[#E8C97A] transition-colors font-medium ml-1">
            Se connecter
        </a>
    </p>
</div>

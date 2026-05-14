<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();
        $this->form->authenticate();
        Session::regenerate();

        $user = auth()->user();

        // Détecter la première connexion (avant mise à jour)
        $isFirstLogin = $user->isClient() && is_null($user->last_login_at);

        // Mettre à jour la date de dernière connexion
        $user->update(['last_login_at' => now()]);

        // Redirection selon le rôle et l'historique :
        //   Admin → /admin/dashboard
        //   Client (1ère fois) → /client/profile
        //   Client (habituel) → /client/orders
        $default = $user->isAdmin()
            ? route('admin.dashboard', absolute: false)
            : ($isFirstLogin ? route('client.profile', absolute: false) : route('client.orders.index', absolute: false));

        $this->redirectIntended(default: $default, navigate: true);
    }
}; ?>

<div>
    {{-- Page title --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-[#F5F0E8] mb-1">Connexion</h1>
        <p class="text-[#7A6E5E] text-sm">Accédez à votre espace OmnyRestore</p>
    </div>

    {{-- Status --}}
    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form wire:submit="login" class="space-y-5">

        {{-- Email --}}
        <div>
            <x-input-label for="email" :value="__('Adresse email')" />
            <x-text-input
                wire:model="form.email"
                id="email" type="email" name="email"
                placeholder="vous@exemple.fr"
                required autofocus autocomplete="username"
            />
            <x-input-error :messages="$errors->get('form.email')" class="mt-1.5" />
        </div>

        {{-- Password --}}
        <div x-data="{ show: false }">
            <div class="flex items-center justify-between mb-1.5">
                <x-input-label for="password" :value="__('Mot de passe')" />
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate
                       class="text-[#C9A84C] text-xs hover:text-[#E8C97A] transition-colors">
                        Mot de passe oublié ?
                    </a>
                @endif
            </div>
            <div class="relative">
                <x-text-input
                    wire:model="form.password"
                    id="password" x-bind:type="show ? 'text' : 'password'" name="password"
                    placeholder="••••••••"
                    required autocomplete="current-password"
                    class="pr-12"
                />
                <button type="button" @click="show = !show"
                        class="absolute right-0 top-0 bottom-0 px-4 flex items-center text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
                    <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.076m1.089-3.34A10.001 10.001 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.059 10.059 0 01-2.015 3.123m-4.656-1.123a3 3 0 11-4.243-4.242M10.477 10.477L13.523 13.523M2 2l20 20"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('form.password')" class="mt-1.5" />
        </div>

        {{-- Remember --}}
        <label for="remember" class="flex items-center gap-3 cursor-pointer">
            <input wire:model="form.remember" id="remember" type="checkbox" name="remember"
                   class="w-4 h-4 rounded-sm border border-[#C9A84C]/30 bg-[#1A1510] text-[#C9A84C]
                          checked:bg-[#C9A84C] focus:ring-[#C9A84C]/30 focus:ring-offset-[#0D0B08]">
            <span class="text-[#7A6E5E] text-sm">Se souvenir de moi</span>
        </label>

        {{-- Submit --}}
        <div class="pt-2">
            <x-primary-button>
                Se connecter
            </x-primary-button>
        </div>

    </form>

    {{-- Register link --}}
    <p class="mt-6 text-center text-sm text-[#7A6E5E]">
        Pas encore de compte ?
        <a href="{{ route('register') }}" wire:navigate class="text-[#C9A84C] hover:text-[#E8C97A] transition-colors font-medium ml-1">
            Créer un compte
        </a>
    </p>
</div>

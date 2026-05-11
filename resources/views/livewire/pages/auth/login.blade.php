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
        $this->redirectIntended(default: route('client.orders.index', absolute: false), navigate: true);
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
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <x-input-label for="password" :value="__('Mot de passe')" />
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate
                       class="text-[#C9A84C] text-xs hover:text-[#E8C97A] transition-colors">
                        Mot de passe oublié ?
                    </a>
                @endif
            </div>
            <x-text-input
                wire:model="form.password"
                id="password" type="password" name="password"
                placeholder="••••••••"
                required autocomplete="current-password"
            />
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

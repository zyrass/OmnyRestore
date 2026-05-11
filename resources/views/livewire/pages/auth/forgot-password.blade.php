<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    public function sendPasswordResetLink(): void
    {
        $this->validate(['email' => ['required', 'string', 'email']]);

        $status = Password::sendResetLink($this->only('email'));

        if ($status == Password::RESET_LINK_SENT) {
            $this->dispatch('password-reset-link-sent');
        }

        session()->flash('status', __($status));
    }
}; ?>

<div>
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-[#F5F0E8] mb-1">Mot de passe oublié</h1>
        <p class="text-[#7A6E5E] text-sm leading-relaxed">
            Saisissez votre email et nous vous enverrons un lien pour réinitialiser votre mot de passe.
        </p>
    </div>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="space-y-5">
        <div>
            <x-input-label for="email" :value="__('Adresse email')" />
            <x-text-input
                wire:model="email" id="email" type="email" name="email"
                placeholder="vous@exemple.fr"
                required autofocus autocomplete="username"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div class="pt-1">
            <x-primary-button>
                Envoyer le lien de réinitialisation
            </x-primary-button>
        </div>
    </form>

    <p class="mt-6 text-center text-sm text-[#7A6E5E]">
        <a href="{{ route('login') }}" wire:navigate class="text-[#C9A84C] hover:text-[#E8C97A] transition-colors">
            ← Retour à la connexion
        </a>
    </p>
</div>

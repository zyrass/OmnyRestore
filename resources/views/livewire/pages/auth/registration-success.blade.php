<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';
    public bool $sent = false;

    public function resend(): void
    {
        $this->validate(['email' => 'required|email']);

        $key = 'resend-verification:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('email', 'Veuillez patienter avant de réessayer.');
            return;
        }
        RateLimiter::hit($key, 60);

        $user = User::where('email', $this->email)->first();

        // Si l'utilisateur existe et n'est pas encore vérifié, on renvoie l'email
        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        // Message générique dans tous les cas (Anti-Enumeration)
        $this->sent = true;
        $this->email = '';
    }
}; ?>

<div>
    <div class="mb-8">
        <div class="w-14 h-14 border border-emerald-500/30 bg-emerald-900/10 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-[#F5F0E8] mb-2 text-center">Vérifiez votre boîte mail</h1>
        <p class="text-[#7A6E5E] text-sm text-center leading-relaxed mb-4">
            Votre compte a été créé avec succès. Un lien d'activation vient de vous être envoyé.
            Cliquez dessus pour valider votre compte.
        </p>
    </div>

    <div class="bg-[#1A1510] border border-[#C9A84C]/20 p-5 rounded-sm">
        <h2 class="text-[#F5F0E8] text-sm font-semibold mb-2">Email introuvable ou perdu ?</h2>
        <p class="text-[#7A6E5E] text-xs mb-4">Saisissez votre adresse email ci-dessous pour recevoir un nouveau lien d'activation.</p>

        @if($sent)
            <div class="mb-4 p-3 bg-emerald-900/20 border border-emerald-500/30 rounded-sm">
                <p class="text-emerald-400 text-xs">Si cette adresse correspond à un compte non vérifié, un nouveau lien vient de vous être envoyé.</p>
            </div>
        @else
            <form wire:submit="resend" class="flex gap-2">
                <div class="flex-1">
                    <input wire:model="email" type="email" placeholder="votre@email.com" required
                           class="w-full bg-[#0F0C08] border border-[#3A3028] focus:border-[#C9A84C]/50 text-[#F5F0E8] text-sm rounded-sm px-3 py-2 transition-colors">
                    @error('email') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled"
                        class="px-4 py-2 bg-gradient-to-r from-[#C9A84C] to-[#E8C97A] hover:opacity-90 text-[#0F0C08] text-sm font-bold rounded-sm transition-opacity whitespace-nowrap">
                    <span wire:loading.remove wire:target="resend">Renvoyer</span>
                    <span wire:loading wire:target="resend">Envoi...</span>
                </button>
            </form>
        @endif
    </div>

    <div class="mt-6 text-center">
        <a href="{{ route('login') }}" wire:navigate class="text-[#C9A84C] hover:text-[#E8C97A] text-sm font-medium transition-colors">
            ← Retour à la page de connexion
        </a>
    </div>
</div>

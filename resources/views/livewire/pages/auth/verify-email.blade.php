<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('client.orders.index', absolute: false), navigate: true);
            return;
        }

        Auth::user()->sendEmailVerificationNotification();
        Session::flash('status', 'Un nouveau lien de vérification a été envoyé à votre adresse email.');
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();
        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <div class="mb-8">
        <div class="w-14 h-14 border border-[#C9A84C]/30 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-6 h-6 text-[#C9A84C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-[#F5F0E8] mb-2 text-center">Vérifiez votre email</h1>
        <p class="text-[#7A6E5E] text-sm text-center leading-relaxed">
            Un lien de vérification a été envoyé à votre adresse email.
            Cliquez sur ce lien pour activer votre compte.
        </p>
    </div>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form wire:submit="sendVerification">
        <x-primary-button>
            Renvoyer le lien de vérification
        </x-primary-button>
    </form>

    <div class="mt-4 text-center">
        <button wire:click="logout" class="text-[#7A6E5E] text-sm hover:text-[#C9A84C] transition-colors">
            Se déconnecter
        </button>
    </div>
</div>

<?php
/**
 * Client — Page de profil / RGPD
 * Route: GET /client/profile
 * Middleware: auth, verified
 */

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Mon profil')]
class extends Component {}; ?>

<div>
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-[#F5F0E8]">Mon profil</h1>
        <p class="text-[#7A6E5E] text-sm mt-1">Gérez vos informations personnelles et vos données</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">

            {{-- Profil --}}
            <div class="card-glass p-6">
                <h2 class="text-[#F5F0E8] font-semibold mb-5">Informations personnelles</h2>
                <div class="space-y-4">
                    <div>
                        <x-input-label for="name" :value="__('Nom complet')" />
                        <x-text-input id="name" type="text" :value="auth()->user()->name" disabled class="opacity-60" />
                    </div>
                    <div>
                        <x-input-label for="email" :value="__('Adresse email')" />
                        <x-text-input id="email" type="email" :value="auth()->user()->email" disabled class="opacity-60" />
                    </div>
                </div>
                <p class="text-[#7A6E5E] text-xs mt-4">
                    Pour modifier vos informations, contactez
                    <a href="mailto:contact@omnyrestore.fr" class="text-[#C9A84C] hover:text-[#E8C97A] transition-colors">contact@omnyrestore.fr</a>
                </p>
            </div>

            {{-- RGPD --}}
            <div class="card-glass p-6">
                <h2 class="text-[#F5F0E8] font-semibold mb-2">Mes droits RGPD</h2>
                <p class="text-[#7A6E5E] text-xs mb-5">Conformément au RGPD, vous disposez des droits suivants sur vos données personnelles.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach([
                        ['icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z', 'title' => 'Droit d\'accès', 'desc' => 'Obtenir une copie de vos données'],
                        ['icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'title' => 'Droit de rectification', 'desc' => 'Corriger vos données inexactes'],
                        ['icon' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16', 'title' => 'Droit à l\'effacement', 'desc' => 'Supprimer votre compte et vos données'],
                        ['icon' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4', 'title' => 'Droit à la portabilité', 'desc' => 'Exporter vos données (JSON)'],
                    ] as $right)
                    <div class="flex items-start gap-3 p-3 bg-[#1A1510] rounded-sm border border-[#C9A84C]/10">
                        <svg class="w-4 h-4 text-[#C9A84C] mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $right['icon'] }}"/></svg>
                        <div>
                            <p class="text-[#F5F0E8] text-xs font-medium">{{ $right['title'] }}</p>
                            <p class="text-[#7A6E5E] text-xs">{{ $right['desc'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="mt-4 pt-4 border-t border-[#C9A84C]/10">
                    <p class="text-[#7A6E5E] text-xs">
                        Pour exercer vos droits :
                        <a href="mailto:privacy@omnyrestore.fr" class="text-[#C9A84C] hover:text-[#E8C97A] transition-colors">privacy@omnyrestore.fr</a>
                        — Délai de réponse : 1 mois (RGPD Art. 12)
                    </p>
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <div class="card-glass p-5">
                <h3 class="text-[#7A6E5E] text-xs tracking-widest uppercase mb-4">Votre compte</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-[#7A6E5E]">Membre depuis</span>
                        <span class="text-[#F5F0E8]">{{ auth()->user()->created_at->format('M Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#7A6E5E]">Email vérifié</span>
                        <span class="{{ auth()->user()->hasVerifiedEmail() ? 'text-emerald-400' : 'text-yellow-400' }} text-xs">
                            {{ auth()->user()->hasVerifiedEmail() ? '✓ Oui' : '⚠ Non' }}
                        </span>
                    </div>
                    @if (auth()->user()->rgpd_consent_at)
                    <div class="flex justify-between">
                        <span class="text-[#7A6E5E]">Consentement RGPD</span>
                        <span class="text-emerald-400 text-xs">{{ auth()->user()->rgpd_consent_at->format('d/m/Y') }}</span>
                    </div>
                    @endif
                </div>
            </div>
            <div class="card-glass p-5 border-red-500/15">
                <h3 class="text-red-400 text-xs tracking-widest uppercase mb-3">Zone critique</h3>
                <p class="text-[#7A6E5E] text-xs mb-4">
                    La suppression de votre compte est <strong class="text-red-400">irréversible</strong>.
                    Vos photos seront supprimées immédiatement et vos données anonymisées.
                </p>
                <a href="{{ route('client.account.delete') }}"
                   class="block text-center px-4 py-2 text-xs border border-red-500/30 text-red-400
                          hover:bg-red-500/10 hover:border-red-500/50 rounded-sm transition-all">
                    Supprimer mon compte →
                </a>
            </div>
        </div>
    </div>
</div>

<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form wire:submit="updatePassword" class="mt-6 space-y-6">
        <div x-data="{ show: false }">
            <x-input-label for="update_password_current_password" :value="__('Mot de passe actuel')" />
            <div class="relative">
                <x-text-input wire:model="current_password" id="update_password_current_password" name="current_password" x-bind:type="show ? 'text' : 'password'" class="mt-1 block w-full pr-12" autocomplete="current-password" />
                <button type="button" @click="show = !show"
                        class="absolute right-0 top-0 bottom-0 px-4 flex items-center text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
                    <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.076m1.089-3.34A10.001 10.001 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.059 10.059 0 01-2.015 3.123m-4.656-1.123a3 3 0 11-4.243-4.242M10.477 10.477L13.523 13.523M2 2l20 20"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div x-data="{ show: false }">
            <x-input-label for="update_password_password" :value="__('Nouveau mot de passe')" />
            <div class="relative">
                <x-text-input wire:model="password" id="update_password_password" name="password" x-bind:type="show ? 'text' : 'password'" class="mt-1 block w-full pr-12" autocomplete="new-password" />
                <button type="button" @click="show = !show"
                        class="absolute right-0 top-0 bottom-0 px-4 flex items-center text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
                    <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.076m1.089-3.34A10.001 10.001 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.059 10.059 0 01-2.015 3.123m-4.656-1.123a3 3 0 11-4.243-4.242M10.477 10.477L13.523 13.523M2 2l20 20"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div x-data="{ show: false }">
            <x-input-label for="update_password_password_confirmation" :value="__('Confirmer le mot de passe')" />
            <div class="relative">
                <x-text-input wire:model="password_confirmation" id="update_password_password_confirmation" name="password_confirmation" x-bind:type="show ? 'text' : 'password'" class="mt-1 block w-full pr-12" autocomplete="new-password" />
                <button type="button" @click="show = !show"
                        class="absolute right-0 top-0 bottom-0 px-4 flex items-center text-[#7A6E5E] hover:text-[#C9A84C] transition-colors">
                    <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.076m1.089-3.34A10.001 10.001 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.059 10.059 0 01-2.015 3.123m-4.656-1.123a3 3 0 11-4.243-4.242M10.477 10.477L13.523 13.523M2 2l20 20"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            <x-action-message class="me-3" on="password-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>

<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.register');
    }

    public function test_new_users_can_register(): void
    {
        // Mot de passe conforme CNIL (12+ chars, majuscule, chiffre, symbole)
        // Consentement RGPD obligatoire (champ rgpd_consent accepté)
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'R3gist3r!Secur3#99')
            ->set('password_confirmation', 'R3gist3r!Secur3#99')
            ->set('rgpd_consent', true); // Obligatoire pour s'inscrire

        $component->call('register');

        // L'app redirige les nouveaux clients vers /client/orders (pas /dashboard)
        // Voir resources/views/livewire/pages/auth/register.blade.php
        $component->assertRedirect(route('client.orders.index', absolute: false));

        $this->assertAuthenticated();
    }
}

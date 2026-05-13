<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de mise à jour du mot de passe.
 *
 * Note : le nouveau mot de passe doit respecter la politique CNIL configurée
 * dans AppServiceProvider::boot() → Password::defaults() :
 *   - Minimum 12 caractères
 *   - Majuscules ET minuscules
 *   - Au moins un chiffre
 *   - Au moins un symbole
 * Le mot de passe utilisé dans les tests doit donc satisfaire ces règles.
 */
class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        // Nouveau mot de passe conforme à la politique CNIL :
        // ≥12 chars, majuscule, minuscule, chiffre, symbole
        $component = Volt::test('profile.update-password-form')
            ->set('current_password', 'password')
            ->set('password', 'N0uv3au!M0tD3Passe')
            ->set('password_confirmation', 'N0uv3au!M0tD3Passe')
            ->call('updatePassword');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertTrue(Hash::check('N0uv3au!M0tD3Passe', $user->refresh()->password));
    }

    #[Test]
    public function correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-password-form')
            ->set('current_password', 'wrong-password')
            ->set('password', 'N0uv3au!M0tD3Passe')
            ->set('password_confirmation', 'N0uv3au!M0tD3Passe')
            ->call('updatePassword');

        $component
            ->assertHasErrors(['current_password'])
            ->assertNoRedirect();
    }
}

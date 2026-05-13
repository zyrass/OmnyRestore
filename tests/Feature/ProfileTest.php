<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create(); // role='client' par défaut

        // /client/profile est la page de profil OmnyRestore (pas Breeze /profile)
        // Elle est implémentée comme un composant Volt unique (pages.client.profile)
        // Les sous-composants Breeze (update-profile-form, etc.) sont inclus via @livewire
        // mais peuvent ne pas être détectables via assertSeeVolt selon l'implémentation
        $response = $this->actingAs($user)->get('/client/profile');

        $response->assertOk();
        // Vérifier que le titre de la page s'affiche correctement
        $response->assertSee('Mon profil');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-profile-information-form')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-profile-information-form')
            ->set('name', 'Test User')
            ->set('email', $user->email)
            ->call('updateProfileInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();

        // Note : le composant Breeze 'delete-user-form' effectue un simple soft-delete ($user->delete()).
        // Il ne passe PAS par DeleteUserAction (qui anonymise les PII et sette anonymized_at).
        // Le flow RGPD complet avec anonymisation est dans /client/account/delete.
        // Ici on vérifie uniquement que le compte a été soft-delete.
        $deletedUser = User::withTrashed()->find($user->id);
        $this->assertNotNull($deletedUser);
        $this->assertNotNull($deletedUser->deleted_at);
    }

    public function test_user_can_delete_their_account_with_full_anonymization(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Créer une commande avec instructions
        $order = \App\Models\Order::factory()->create([
            'user_id' => $user->id,
            'instructions' => 'Veuillez garder le grain de peau naturel.',
        ]);

        // Créer un témoignage
        \App\Models\Testimonial::create([
            'user_id' => $user->id,
            'author_name' => 'John Doe',
            'author_initials' => 'JD',
            'rating' => 5,
            'content' => 'Super service, je recommande !',
            'is_published' => true,
        ]);

        $this->actingAs($user);

        // On simule l'appel au composant de suppression complète
        $component = Volt::test('pages.client.account.delete')
            ->set('password', 'password')
            ->set('confirmed', true)
            ->call('deleteAccount');

        $component->assertRedirect('/');
        $this->assertGuest();

        $user->refresh();

        // Vérifications de l'anonymisation du profil
        $this->assertTrue($user->trashed());
        $this->assertNotNull($user->anonymized_at);
        $this->assertSame('Utilisateur supprimé', $user->name);
        $this->assertStringContainsString('@data.deleted', $user->email);

        // Vérifier que les instructions de commande sont effacées
        $this->assertNull($order->fresh()->instructions);

        // Vérifier que les témoignages sont supprimés (purge RGPD)
        $this->assertDatabaseMissing('testimonials', [
            'user_id' => $user->id,
        ]);
    }
}

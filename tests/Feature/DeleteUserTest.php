<?php

namespace Tests\Feature;

use App\Actions\DeleteUserAction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Test : Suppression de compte RGPD (Art. 17)
 *
 * Vérifie que DeleteUserAction :
 *   - Anonymise correctement toutes les PII
 *   - Conserve les commandes (obligation comptable)
 *   - Enregistre anonymized_at pour l'audit trail
 *   - Le soft-delete exclut l'utilisateur des requêtes normales
 */
class DeleteUserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function delete_user_action_anonymizes_pii(): void
    {
        $user = User::factory()->create([
            'name'  => 'Jean Dupont',
            'email' => 'jean.dupont@example.com',
        ]);

        (new DeleteUserAction())->execute($user, 'password'); // mot de passe par défaut du factory

        // L'utilisateur ne doit plus être trouvable par ses données d'origine
        $this->assertNull(User::where('email', 'jean.dupont@example.com')->first());

        // Soft-deleted : visible via withTrashed()
        $deleted = User::withTrashed()->find($user->id);
        $this->assertNotNull($deleted);
        $this->assertNotNull($deleted->deleted_at);
        $this->assertNotNull($deleted->anonymized_at);

        // Les PII doivent être anonymisées
        $this->assertStringContainsString('@data.deleted', $deleted->email);
        $this->assertStringNotContainsString('jean.dupont', $deleted->email);
    }

    /** @test */
    public function orders_are_preserved_after_user_deletion(): void
    {
        $user = User::factory()->create();

        Order::create([
            'user_id'        => $user->id,
            'description'    => 'Commande test',
            'photo_count'    => 1,
            'status'         => 'DELIVERED',
            'payment_status' => 'paid',
        ]);

        (new DeleteUserAction())->execute($user, 'password');

        // Les commandes sont conservées (obligation comptable 10 ans)
        $this->assertEquals(1, Order::count());
    }

    /** @test */
    public function wrong_password_prevents_deletion(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('BonMotDePasse123!'),
        ]);

        $this->expectException(\InvalidArgumentException::class);

        (new DeleteUserAction())->execute($user, 'MauvaisMotDePasse');
    }
}

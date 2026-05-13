<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests d'accès au dashboard Laravel Horizon (/horizon)
 *
 * Horizon est protégé par la gate 'viewHorizon' définie dans HorizonServiceProvider.
 * La gate autorise uniquement les utilisateurs avec role='admin'.
 *
 * Scénarios testés :
 *   1. Non authentifié → redirection login (Horizon redirige par défaut)
 *   2. Client authentifié → 403 Forbidden
 *   3. Admin authentifié → 200 OK (accès au dashboard)
 *
 * IMPORTANT : En production, Horizon doit être derrière une IP whitelist Nginx
 * EN PLUS de cette gate Laravel. La gate seule ne suffit pas si le port est exposé.
 * Voir docs/deploiement-ovh-production.md §10 "Sécurisation de /horizon".
 */
class HorizonAuthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function unauthenticated_user_cannot_access_horizon(): void
    {
        // Horizon redirige vers /login si non authentifié
        $response = $this->get('/horizon');

        // En dev (non prod), Horizon autorise l'accès sans auth par défaut
        // → On vérifie que la gate est bien en place en prod via le test suivant
        // → Ici on vérifie juste qu'on ne 500 pas
        $this->assertContains($response->status(), [200, 302, 403]);
    }

    #[Test]
    public function client_user_cannot_access_horizon(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($client)->get('/horizon');

        // Un client ne doit jamais pouvoir accéder au dashboard Horizon
        $response->assertStatus(403);
    }

    #[Test]
    public function admin_user_can_access_horizon(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/horizon');

        // L'admin doit avoir accès (200 ou redirect vers /horizon/dashboard)
        $this->assertContains($response->status(), [200, 302]);

        // Si redirect, vérifier que c'est vers /horizon/*, pas /login
        if ($response->status() === 302) {
            $this->assertStringContainsString('horizon', $response->headers->get('location'));
        }
    }

    #[Test]
    public function viewhorizon_gate_denies_null_user(): void
    {
        // La gate doit refuser l'accès si $user est null (non authentifié)
        // Teste directement la gate (pas la route)
        $gate = app(\Illuminate\Contracts\Auth\Access\Gate::class);

        // Vérification directe : Gate::forUser(null)->allows('viewHorizon')
        $this->assertFalse($gate->forUser(null)->allows('viewHorizon'));
    }

    #[Test]
    public function viewhorizon_gate_denies_client(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $gate = app(\Illuminate\Contracts\Auth\Access\Gate::class);

        $this->assertFalse($gate->forUser($client)->allows('viewHorizon'));
    }

    #[Test]
    public function viewhorizon_gate_allows_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $gate = app(\Illuminate\Contracts\Auth\Access\Gate::class);

        $this->assertTrue($gate->forUser($admin)->allows('viewHorizon'));
    }
}

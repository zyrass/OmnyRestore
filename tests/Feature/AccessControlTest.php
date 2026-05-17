<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test : Contrôle d'accès (Admin vs Client)
 *
 * Vérifie que :
 *   - Les routes /admin/* sont inaccessibles aux clients
 *   - Les routes /client/* sont inaccessibles aux admins
 *   - Un utilisateur non authentifié est redirigé vers /login
 *   - La protection IDOR empêche un client de voir la commande d'un autre
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function makeClient(): User
    {
        return User::factory()->create(['role' => 'client']);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** @test */
    public function unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get('/client/orders')->assertRedirect('/login');
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    /** @test */
    public function client_cannot_access_admin_routes(): void
    {
        $client = $this->makeClient();

        $this->actingAs($client)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    /** @test */
    public function admin_can_access_admin_dashboard(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertSuccessful();
    }

    /** @test */
    public function client_cannot_view_another_clients_order(): void
    {
        $owner = $this->makeClient();
        $other = $this->makeClient();

        $order = Order::create([
            'user_id'        => $owner->id,
            'description'    => 'Commande privée',
            'photo_count'    => 2,
            'status'         => 'PENDING',
            'payment_status' => 'pending',
        ]);

        // L'autre client tente d'accéder à la commande → doit être interdit
        $this->actingAs($other)
            ->get("/client/orders/{$order->id}")
            ->assertForbidden();
    }

    /** @test */
    public function order_owner_can_view_their_own_order(): void
    {
        $owner = $this->makeClient();

        $order = Order::create([
            'user_id'        => $owner->id,
            'description'    => 'Ma commande',
            'photo_count'    => 1,
            'status'         => 'PENDING',
            'payment_status' => 'pending',
        ]);

        $this->actingAs($owner)
            ->get("/client/orders/{$order->id}")
            ->assertSuccessful();
    }

    /** @test */
    public function marketing_cannot_access_orders_or_tickets(): void
    {
        $marketing = User::factory()->create(['role' => 'marketing', 'email_verified_at' => now()]);

        $this->actingAs($marketing)->get('/admin/orders')->assertForbidden();
        $this->actingAs($marketing)->get('/admin/tickets')->assertForbidden();
    }

    /** @test */
    public function operator_cannot_access_coupons_or_testimonials(): void
    {
        $operator = User::factory()->create(['role' => 'operator', 'email_verified_at' => now()]);

        $this->actingAs($operator)->get('/admin/coupons')->assertForbidden();
        $this->actingAs($operator)->get('/admin/testimonials')->assertForbidden();
    }

    /** @test */
    public function operator_can_access_orders_and_tickets(): void
    {
        $operator = User::factory()->create(['role' => 'operator', 'email_verified_at' => now()]);

        $this->actingAs($operator)->get('/admin/orders')->assertSuccessful();
        $this->actingAs($operator)->get('/admin/tickets')->assertSuccessful();
    }

    /** @test */
    public function marketing_can_access_coupons_and_testimonials(): void
    {
        $marketing = User::factory()->create(['role' => 'marketing', 'email_verified_at' => now()]);

        $this->actingAs($marketing)->get('/admin/coupons')->assertSuccessful();
        $this->actingAs($marketing)->get('/admin/testimonials')->assertSuccessful();
    }
}

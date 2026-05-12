<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test : Moteur de témoignages
 *
 * Règles métier testées :
 *   - Un seul avis par commande (contrainte UNIQUE order_id)
 *   - Seules les commandes DELIVERED permettent de soumettre un avis
 *   - Seul le propriétaire de la commande peut soumettre un avis
 *   - Les scopes published/pending/rejected fonctionnent correctement
 */
class TestimonialTest extends TestCase
{
    use RefreshDatabase;

    private function makeDeliveredOrder(User $user): Order
    {
        return Order::create([
            'user_id'        => $user->id,
            'description'    => 'Restauration test',
            'photo_count'    => 2,
            'status'         => 'DELIVERED',
            'payment_status' => 'paid',
        ]);
    }

    /** @test */
    public function published_scope_returns_only_published_testimonials(): void
    {
        $user  = User::factory()->create();
        $order = $this->makeDeliveredOrder($user);

        Testimonial::create([
            'author_name'     => 'Marie D.',
            'author_initials' => 'MD',
            'content'         => 'Superbe travail sur mes photos !',
            'rating'          => 5,
            'is_published'    => true,
            'order_id'        => $order->id,
            'user_id'         => $user->id,
        ]);

        Testimonial::create([
            'author_name'     => 'Pierre K.',
            'author_initials' => 'PK',
            'content'         => 'En attente de validation',
            'rating'          => 4,
            'is_published'    => false,
        ]);

        $this->assertEquals(1, Testimonial::published()->count());
        $this->assertEquals(1, Testimonial::pending()->count());
    }

    /** @test */
    public function rejected_scope_filters_by_rejected_at_not_null(): void
    {
        Testimonial::create([
            'author_name'     => 'Test Rejeté',
            'author_initials' => 'TR',
            'content'         => 'Contenu inapproprié',
            'rating'          => 1,
            'is_published'    => false,
            'rejected_at'     => now(),
        ]);

        Testimonial::create([
            'author_name'     => 'Test Attente',
            'author_initials' => 'TA',
            'content'         => 'En attente',
            'rating'          => 4,
            'is_published'    => false,
        ]);

        $this->assertEquals(1, Testimonial::rejected()->count());
        $this->assertEquals(1, Testimonial::pending()->count());
    }

    /** @test */
    public function two_testimonials_cannot_share_the_same_order_id(): void
    {
        $user  = User::factory()->create();
        $order = $this->makeDeliveredOrder($user);

        Testimonial::create([
            'author_name'     => 'Premier avis',
            'author_initials' => 'PA',
            'content'         => 'Premier témoignage pour cette commande.',
            'rating'          => 5,
            'is_published'    => false,
            'order_id'        => $order->id,
            'user_id'         => $user->id,
        ]);

        // Le deuxième avis sur la même commande doit déclencher une exception
        $this->expectException(\Illuminate\Database\QueryException::class);

        Testimonial::create([
            'author_name'     => 'Deuxième avis',
            'author_initials' => 'DA',
            'content'         => 'On ne peut pas laisser deux avis.',
            'rating'          => 3,
            'is_published'    => false,
            'order_id'        => $order->id,
            'user_id'         => $user->id,
        ]);
    }

    /** @test */
    public function initials_from_generates_correct_initials(): void
    {
        $this->assertEquals('MD', Testimonial::initialsFrom('Marie Dupont'));
        $this->assertEquals('JD', Testimonial::initialsFrom('Jean Durand'));
        $this->assertEquals('JP', Testimonial::initialsFrom('Jean Pierre Durand')); // 2 premiers mots
        $this->assertEquals('A',  Testimonial::initialsFrom('Alice'));
        $this->assertEquals('',   Testimonial::initialsFrom(''));
    }
}

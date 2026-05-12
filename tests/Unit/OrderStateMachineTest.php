<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test : Machine d'état des commandes
 *
 * Vérifie que les transitions de statut sont correctement gardées.
 * Ces règles sont critiques — une mauvaise transition pourrait permettre
 * de payer une commande annulée ou de livrer sans paiement.
 */
class OrderStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $status): Order
    {
        $user = User::factory()->create();

        return Order::create([
            'user_id'        => $user->id,
            'description'    => 'Test commande',
            'photo_count'    => 3,
            'status'         => $status,
            'payment_status' => 'pending',
        ]);
    }

    /** @test */
    public function pending_order_can_start_processing(): void
    {
        $order = $this->makeOrder('PENDING');

        $order->startProcessing();

        $this->assertEquals('IN_PROGRESS', $order->fresh()->status);
    }

    /** @test */
    public function in_progress_order_can_be_marked_done(): void
    {
        $order = $this->makeOrder('IN_PROGRESS');

        $order->markAsDone();

        $this->assertEquals('DONE', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->delivered_at);
    }

    /** @test */
    public function done_order_cannot_start_processing_again(): void
    {
        $order = $this->makeOrder('DONE');

        $this->expectException(\InvalidArgumentException::class);

        $order->startProcessing();
    }

    /** @test */
    public function cancelled_order_cannot_be_marked_done(): void
    {
        $order = $this->makeOrder('CANCELLED');

        $this->expectException(\InvalidArgumentException::class);

        $order->markAsDone();
    }

    /** @test */
    public function pending_or_in_progress_order_can_be_cancelled(): void
    {
        foreach (['PENDING', 'IN_PROGRESS'] as $status) {
            $order = $this->makeOrder($status);
            $order->cancel('Test annulation');

            $this->assertEquals('CANCELLED', $order->fresh()->status);
            $this->assertEquals('Test annulation', $order->fresh()->admin_notes);
        }
    }

    /** @test */
    public function mark_as_paid_stores_payment_intent_and_timestamp(): void
    {
        $order = $this->makeOrder('DONE');
        $order->markAsPaid('pi_test_123456');

        $fresh = $order->fresh();
        $this->assertEquals('pi_test_123456', $fresh->payment_intent_id);
        $this->assertEquals('paid', $fresh->payment_status);
        $this->assertNotNull($fresh->paid_at);
    }

    /** @test */
    public function amount_in_cents_converts_correctly(): void
    {
        $order = $this->makeOrder('PENDING');
        $order->amount_ttc = 49.90;

        // 49.90 € → 4990 centimes
        $this->assertEquals(4990, $order->getAmountInCents());
    }
}

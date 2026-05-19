<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Mail\LoyaltyRewardEarned;
use App\Services\CouponService;
use App\Services\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoyaltySystemTest extends TestCase
{
    use RefreshDatabase;

    private function createClient(): User
    {
        return User::factory()->create(['role' => 'client']);
    }

    private function createOrder(User $user, int $amountTtcCents, string $status = 'PAID'): Order
    {
        $order = Order::create([
            'user_id' => $user->id,
            'photo_count' => 1,
            'damage_level' => 'light',
            'base_price_cents' => $amountTtcCents,
            'total_price_cents' => $amountTtcCents,
            'client_ip' => '127.0.0.1',
        ]);

        $order->forceFill([
            'status' => $status,
            'payment_status' => in_array($status, ['PAID', 'DELIVERED']) ? 'paid' : 'pending',
        ])->save();

        return $order;
    }

    /** @test */
    public function it_counts_eligible_orders_correctly()
    {
        $user = $this->createClient();

        // Orders that should count (status PAID/DELIVERED, amount >= 10.00 € / 1000 cents)
        $this->createOrder($user, 1000, 'PAID'); // counts
        $this->createOrder($user, 1500, 'DELIVERED'); // counts

        // Orders that should NOT count
        $this->createOrder($user, 999, 'PAID'); // amount < 10.00 €
        $this->createOrder($user, 1200, 'PENDING'); // wrong status
        $this->createOrder($user, 1200, 'CANCELED'); // wrong status

        $this->assertEquals(2, $user->eligibleOrdersCount());
        $this->assertEquals(2, $user->loyaltyProgress());
    }

    /** @test */
    public function it_calculates_loyalty_progress_modulo_3()
    {
        $user = $this->createClient();

        $this->assertEquals(0, $user->loyaltyProgress());

        $this->createOrder($user, 1000);
        $this->assertEquals(1, $user->loyaltyProgress());

        $this->createOrder($user, 1000);
        $this->assertEquals(2, $user->loyaltyProgress());

        $this->createOrder($user, 1000);
        // After 3 orders, the loyaltyProgress resets to 0 (since 3 / 3 = 1 coupon, 3 % 3 = 0)
        $this->assertEquals(0, $user->loyaltyProgress());
    }

    /** @test */
    public function it_emits_loyalty_coupon_after_3_eligible_orders_and_sends_email()
    {
        Mail::fake();

        $user = $this->createClient();
        $loyaltyService = app(LoyaltyService::class);

        // 1st order
        $this->createOrder($user, 1000);
        $loyaltyService->checkAndReward($user);
        $this->assertEquals(0, $user->coupons()->count());

        // 2nd order
        $this->createOrder($user, 1200);
        $loyaltyService->checkAndReward($user);
        $this->assertEquals(0, $user->coupons()->count());

        // 3rd order
        $this->createOrder($user, 1500);
        $loyaltyService->checkAndReward($user);

        // Check coupon emission
        $this->assertEquals(1, $user->coupons()->count());

        $coupon = $user->coupons()->first();
        $this->assertStringStartsWith('FID50-', $coupon->code);
        $this->assertEquals(50, $coupon->value);
        $this->assertEquals('percentage', $coupon->type);
        $this->assertTrue($coupon->is_loyalty);
        $this->assertNotNull($coupon->expires_at);
        $this->assertTrue($coupon->expires_at->isAfter(now()->addDays(28)));

        // Verify email was sent
        Mail::assertSent(LoyaltyRewardEarned::class, function ($mail) use ($user, $coupon) {
            return $mail->hasTo($user->email) && $mail->coupon->id === $coupon->id;
        });
    }

    /** @test */
    public function the_reward_check_is_perfectly_self_healing_and_prevents_duplicate_emission()
    {
        $user = $this->createClient();
        $loyaltyService = app(LoyaltyService::class);

        // 3 eligible orders
        $this->createOrder($user, 1000);
        $this->createOrder($user, 1000);
        $this->createOrder($user, 1000);

        // Check multiple times
        $loyaltyService->checkAndReward($user);
        $loyaltyService->checkAndReward($user);
        $loyaltyService->checkAndReward($user);

        // Only 1 coupon should be emitted
        $this->assertEquals(1, $user->coupons()->count());
    }

    /** @test */
    public function coupon_service_scopes_loyalty_coupons_to_owners()
    {
        $userA = $this->createClient();
        $userB = $this->createClient();

        // Create coupon for userA
        $coupon = Coupon::create([
            'code' => 'FID50-USERA',
            'type' => 'percentage',
            'value' => 50,
            'is_loyalty' => true,
            'user_id' => $userA->id,
            'expires_at' => now()->addMonth(),
        ]);

        $couponService = app(CouponService::class);

        // Applying to userA (owner) - should succeed
        $resultA = $couponService->apply('FID50-USERA', 2000, $userA);
        $this->assertTrue($resultA['valid']);

        // Applying to userB (not owner) - should fail
        $resultB = $couponService->apply('FID50-USERA', 2000, $userB);
        $this->assertFalse($resultB['valid']);

        // Applying with no user - should fail
        $resultNoUser = $couponService->apply('FID50-USERA', 2000, null);
        $this->assertFalse($resultNoUser['valid']);
    }

    /** @test */
    public function order_status_transitions_trigger_loyalty_reward_check_automatically()
    {
        Mail::fake();

        $user = $this->createClient();

        // 1st order paid
        $order1 = $this->createOrder($user, 1000, 'PENDING');
        $order1->forceFill(['status' => 'PAID'])->save();

        // 2nd order paid
        $order2 = $this->createOrder($user, 1000, 'PENDING');
        $order2->forceFill(['status' => 'PAID'])->save();

        // 3rd order reaches DELIVERED status directly
        $order3 = $this->createOrder($user, 1000, 'PENDING');
        $order3->forceFill(['status' => 'DELIVERED'])->save();

        // Loyalty coupon should have been generated automatically by OrderObserver
        $this->assertEquals(1, $user->coupons()->count());
        $coupon = $user->coupons()->first();
        $this->assertStringStartsWith('FID50-', $coupon->code);

        Mail::assertSent(LoyaltyRewardEarned::class);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientCouponReuseTest extends TestCase
{
    use RefreshDatabase;

    private function createClient(): User
    {
        return User::factory()->create(['role' => 'client']);
    }

    /** @test */
    public function it_redirects_back_with_session_flags_when_applied_coupon_is_already_fully_used()
    {
        $client = $this->createClient();

        $coupon = Coupon::create([
            'code' => 'FIFTY-OFF',
            'type' => 'percentage',
            'value' => 50,
            'max_uses' => 1,
            'used_count' => 1, // fully used!
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $client->id,
            'description' => 'Test order',
            'photo_count' => 1,
            'coupon_code' => 'FIFTY-OFF',
            'discount_cents' => 500,
            'total_price_cents' => 500,
        ]);
        $order->forceFill([
            'status' => 'DONE',
            'payment_status' => 'pending',
        ])->save();

        // Try to trigger checkout
        $response = $this->actingAs($client)
            ->post(route('client.orders.checkout', $order));

        // It should redirect back to show page with warning flags
        $response->assertRedirect(route('client.orders.show', $order));
        $response->assertSessionHas('show_coupon_used_warning', true);
        $response->assertSessionHas('warning_coupon_code', 'FIFTY-OFF');
    }

    /** @test */
    public function it_allows_paying_without_coupon_via_livewire_action()
    {
        $client = $this->createClient();

        $order = Order::create([
            'user_id' => $client->id,
            'description' => 'Test order',
            'photo_count' => 1,
            'coupon_code' => 'FIFTY-OFF',
            'discount_cents' => 500,
            'total_price_cents' => 500,
        ]);
        $order->forceFill([
            'status' => 'DONE',
            'payment_status' => 'pending',
        ])->save();

        // Call the Livewire component and trigger payWithoutCoupon
        $component = Livewire::actingAs($client)
            ->test('pages.client.orders.show', ['order' => $order])
            ->call('payWithoutCoupon');

        // Confirm the coupon is removed and price updated in order
        $order->refresh();
        $this->assertNull($order->coupon_code);
        $this->assertEquals(0, $order->discount_cents);

        // Redirected to checkout successfully
        $component->assertRedirect(route('client.orders.checkout', $order));
    }
}

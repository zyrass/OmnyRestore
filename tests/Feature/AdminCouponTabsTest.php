<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCouponTabsTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** @test */
    public function it_renders_coupons_index_page_and_shows_correct_filtered_results_by_tab()
    {
        $admin = $this->createAdmin();

        // 1. Create loyalty, seasonal and promo coupons
        $loyaltyCoupon = Coupon::create([
            'code' => 'FID50-LOYALTY',
            'type' => 'percentage',
            'value' => 50,
            'is_loyalty' => true,
            'is_seasonal' => false,
            'is_active' => true,
        ]);

        $seasonalCoupon = Coupon::create([
            'code' => 'NOEL',
            'type' => 'percentage',
            'value' => 20,
            'is_loyalty' => false,
            'is_seasonal' => true,
            'is_active' => true,
            'starts_at' => now(),
            'expires_at' => now()->addDays(5),
        ]);

        $promoCoupon = Coupon::create([
            'code' => 'BLACKFRIDAY',
            'type' => 'percentage',
            'value' => 30,
            'is_loyalty' => false,
            'is_seasonal' => false,
            'is_active' => true,
        ]);

        // 2. Test default view (should be loyalty tab)
        $component = Livewire::actingAs($admin)
            ->test('pages.admin.coupons.index');

        $component->assertSet('activeTab', 'loyalty');
        $component->assertSee('FID50-LOYALTY');
        $component->assertDontSee('NOEL');
        $component->assertDontSee('BLACKFRIDAY');

        // 3. Test seasonal tab filtering
        $component->set('activeTab', 'seasonal');
        $component->assertSee('NOEL');
        $component->assertDontSee('FID50-LOYALTY');
        $component->assertDontSee('BLACKFRIDAY');

        // 4. Test promo tab filtering
        $component->set('activeTab', 'promo');
        $component->assertSee('BLACKFRIDAY');
        $component->assertDontSee('FID50-LOYALTY');
        $component->assertDontSee('NOEL');
    }

    /** @test */
    public function manually_creating_a_standard_coupon_auto_transitions_active_tab_to_promo()
    {
        $admin = $this->createAdmin();

        $component = Livewire::actingAs($admin)
            ->test('pages.admin.coupons.index')
            ->set('activeTab', 'loyalty')
            ->set('code', 'NEWPROMO')
            ->set('type', 'percentage')
            ->set('value', 15)
            ->set('starts_at', now()->format('Y-m-d'))
            ->set('expires_at', now()->addDays(5)->format('Y-m-d'))
            ->set('is_seasonal', false)
            ->call('createCoupon');

        $component->assertSet('activeTab', 'promo');
        $this->assertTrue(Coupon::where('code', 'NEWPROMO')->exists());
        $coupon = Coupon::where('code', 'NEWPROMO')->first();
        $this->assertFalse($coupon->is_seasonal);
        $this->assertFalse($coupon->is_loyalty);
    }

    /** @test */
    public function manually_creating_a_seasonal_coupon_auto_transitions_active_tab_to_seasonal()
    {
        $admin = $this->createAdmin();

        $component = Livewire::actingAs($admin)
            ->test('pages.admin.coupons.index')
            ->set('activeTab', 'loyalty')
            ->set('code', 'NEWSEASONAL')
            ->set('type', 'fixed')
            ->set('value', 500)
            ->set('starts_at', now()->format('Y-m-d'))
            ->set('expires_at', now()->addDays(2)->format('Y-m-d'))
            ->set('is_seasonal', true)
            ->call('createCoupon');

        $component->assertSet('activeTab', 'seasonal');
        $this->assertTrue(Coupon::where('code', 'NEWSEASONAL')->exists());
        $coupon = Coupon::where('code', 'NEWSEASONAL')->first();
        $this->assertTrue($coupon->is_seasonal);
        $this->assertFalse($coupon->is_loyalty);
    }
}

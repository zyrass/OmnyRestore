<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class ClientOrderShowTest extends TestCase
{
    use RefreshDatabase;

    private function createClient(): User
    {
        return User::factory()->create(['role' => 'client']);
    }

    private function getValidPng(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    }

    /** @test */
    public function it_renders_order_details_and_active_photos_with_zoom_option()
    {
        $client = $this->createClient();

        $order = Order::create([
            'user_id'        => $client->id,
            'description'    => 'Test order',
            'photo_count'    => 1,
            'preview_unlocked_at' => now(),
        ]);
        $order->forceFill([
            'status' => 'DONE',
            'payment_status' => 'pending',
        ])->save();

        // Create a media item belonging to this order
        $media = $order->addMediaFromString($this->getValidPng())
            ->usingFileName('photo.png')
            ->toMediaCollection('retouched');

        $response = $this->actingAs($client)
            ->get("/client/orders/{$order->id}");

        $response->assertStatus(200);

        // Ensure "Agrandir" text is present (our new simplified zoom label)
        $response->assertSee('Agrandir');

        // Ensure the old "Comparer" text is no longer present
        $response->assertDontSee('Comparer');
    }

    /** @test */
    public function it_does_not_render_img_url_or_preview_link_for_rejected_photos()
    {
        $client = $this->createClient();

        $order = Order::create([
            'user_id'        => $client->id,
            'description'    => 'Test order',
            'photo_count'    => 1,
            'preview_unlocked_at' => now(),
        ]);
        $order->forceFill([
            'status' => 'DONE',
            'payment_status' => 'pending',
        ])->save();

        // Create a rejected media item belonging to this order
        $media = $order->addMediaFromString($this->getValidPng())
            ->usingFileName('photo.png')
            ->withCustomProperties(['is_rejected' => true])
            ->toMediaCollection('retouched');

        $response = $this->actingAs($client)
            ->get("/client/orders/{$order->id}");

        $response->assertStatus(200);

        // Calculate the retouched URL that would cause a 404 if accessed
        $retouchedUrl = route('client.orders.photo.show', [$order, $media]);

        // Verify that the view does NOT render an <img> tag with this URL
        // (This guarantees no console 404 errors!)
        $response->assertDontSee('src="' . $retouchedUrl . '"', false);

        // Verify that it renders the premium placeholder (the crossed-eye SVG)
        $response->assertSee('text-red-500/35');

        // Verify that it has the "Réintégrer" action button
        $response->assertSee('Réintégrer');

        // Verify that it hides the delete button ("Supprimer définitivement")
        $response->assertDontSee('Supprimer définitivement');
    }
}

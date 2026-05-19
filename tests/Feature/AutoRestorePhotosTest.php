<?php

namespace Tests\Feature;

use App\Jobs\AutoRestoreOrderPhotosJob;
use App\Models\Order;
use App\Models\User;
use App\Services\PhotoRestorationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class AutoRestorePhotosTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_transitions_order_status_to_done_at_the_end_of_restoration_job()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'PENDING',
        ]);

        // Add a fake original image using Spatie Media Library to trigger processing
        $order->addMedia(\Illuminate\Http\UploadedFile::fake()->image('test.jpg'))
            ->toMediaCollection('originals');

        $this->assertEquals('PENDING', $order->status);

        // Mock PhotoRestorationService so it doesn't make real external API calls
        $mockService = Mockery::mock(PhotoRestorationService::class);
        $mockService->shouldReceive('restore')
            ->once()
            ->andReturn(tempnam(sys_get_temp_dir(), 'test_resto_') . '.jpg');

        // Execute the job
        $job = new AutoRestoreOrderPhotosJob($order);
        app()->call([$job, 'handle'], ['service' => $mockService]);

        // Check that the order's status has correctly transitioned to DONE
        $order->refresh();
        $this->assertEquals('DONE', $order->status);
        $this->assertStringContainsString('[IA] Restauration automatique', $order->admin_notes);
    }

    /** @test */
    public function artisan_command_only_selects_in_progress_orders_by_default()
    {
        $user = User::factory()->create();

        // 1. Order in PENDING status
        $pendingOrder = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'PENDING',
        ]);
        $pendingOrder->addMedia(\Illuminate\Http\UploadedFile::fake()->image('p.jpg'))
            ->toMediaCollection('originals');

        // 2. Order in IN_PROGRESS status
        $inProgressOrder = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'IN_PROGRESS',
        ]);
        $inProgressOrder->addMedia(\Illuminate\Http\UploadedFile::fake()->image('i.jpg'))
            ->toMediaCollection('originals');

        // 3. Order in DONE status (already treated)
        $doneOrder = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'DONE',
        ]);
        $doneOrder->addMedia(\Illuminate\Http\UploadedFile::fake()->image('d.jpg'))
            ->toMediaCollection('originals');

        Queue::fake();

        // Run the photos:restore command
        $this->artisan('photos:restore')
            ->assertExitCode(0);

        // Verify that only the IN_PROGRESS order was selected and queued for restoration,
        // and the already completed (DONE) order was NOT selected.
        Queue::assertPushed(AutoRestoreOrderPhotosJob::class, function ($job) use ($inProgressOrder, $pendingOrder, $doneOrder) {
            return $job->order->id === $inProgressOrder->id;
        });

        Queue::assertNotPushed(AutoRestoreOrderPhotosJob::class, function ($job) use ($doneOrder) {
            return $job->order->id === $doneOrder->id;
        });
        
        Queue::assertNotPushed(AutoRestoreOrderPhotosJob::class, function ($job) use ($pendingOrder) {
            return $job->order->id === $pendingOrder->id;
        });
    }
}

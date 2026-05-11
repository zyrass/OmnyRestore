<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderDelivery;
use App\Notifications\OrderReadyForPayment;
use App\Services\AuditService;
use App\Services\SignedUrlService;
use App\Services\ZipGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job: GenerateOrderZipJob
 *
 * Asynchronously generates the ZIP archive for a completed order.
 * This job runs in the background queue (Redis + Horizon) after admin marks order DONE.
 *
 * Why async?
 *   Generating a ZIP from multiple 8K photos can take 30-120 seconds and use
 *   several hundred MB of memory. Running this synchronously would:
 *     - Cause a browser timeout (504 Gateway Timeout after 30s)
 *     - Block the admin's HTTP request
 *   By dispatching to a queue, the admin gets instant feedback while the job
 *   runs on a dedicated worker process.
 *
 * Dispatch example (in Livewire AdminOrderManage component):
 *   GenerateOrderZipJob::dispatch($order)
 *       ->onQueue('zip-generation')  // Dedicated queue for resource-intensive jobs
 *       ->delay(now()->addSeconds(5)); // Small delay to ensure media is fully uploaded
 *
 * Failure handling:
 *   - $tries = 3: The job retries up to 3 times on failure
 *   - $backoff: Progressive delay between retries (exponential backoff)
 *   - failed(): Called when all retries are exhausted — notifies admin
 *
 * @see App\Services\ZipGeneratorService
 * @see https://laravel.com/docs/queues
 */
class GenerateOrderZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts before marking as failed.
     * 3 attempts allows recovery from transient S3 or memory errors.
     */
    public int $tries = 3;

    /**
     * Progressive backoff delays between retries (in seconds).
     * Attempt 1 fails → wait 30s → Attempt 2
     * Attempt 2 fails → wait 60s → Attempt 3
     */
    public array $backoff = [30, 60];

    /**
     * Maximum execution time in seconds before the job is considered timed out.
     * 10 minutes is generous for large photo batches (up to ~20 photos).
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     *
     * The Order model is automatically serialized/deserialized by SerializesModels.
     * This means the full model is not stored in the queue — only the ID.
     * Laravel re-fetches the Order from the DB when the job runs.
     *
     * @param Order $order The completed order to generate a ZIP for
     */
    public function __construct(
        public readonly Order $order
    ) {}

    /**
     * Execute the job.
     *
     * Laravel injects services from the container automatically via method injection.
     *
     * @param ZipGeneratorService $zipGenerator Handles the ZipArchive + S3 upload
     * @param SignedUrlService    $signedUrl    Generates the presigned download URL
     * @param AuditService       $audit        Records the completion in audit logs
     */
    public function handle(
        ZipGeneratorService $zipGenerator,
        SignedUrlService    $signedUrl,
        AuditService        $audit
    ): void {
        // Re-fetch order to get latest state (may have changed since job was queued)
        $order = $this->order->fresh();

        // Safety check: only process DONE orders
        if ($order->status !== 'DONE') {
            // Log and silently skip — don't throw (would trigger retry for a non-error)
            logger()->warning("GenerateOrderZipJob skipped: order {$order->reference} is not DONE", [
                'order_id' => $order->id,
                'status'   => $order->status,
            ]);
            return;
        }

        logger()->info("GenerateOrderZipJob started for order {$order->reference}", [
            'order_id'    => $order->id,
            'photo_count' => $order->photo_count,
        ]);

        // ─── Step 1: Generate the ZIP file ────────────────────────────────
        $delivery = $zipGenerator->generate($order);

        // ─── Step 2: Generate the initial presigned download URL ──────────
        $signedUrl->generate($delivery);

        // ─── Step 3: Notify the client that their order is ready ─────────
        // The notification includes the payment link (Stripe Checkout URL)
        $order->user->notify(new OrderReadyForPayment($order));

        logger()->info("GenerateOrderZipJob completed for order {$order->reference}", [
            'order_id'  => $order->id,
            'zip_path'  => $delivery->zip_path,
            'zip_size'  => $delivery->zip_size,
        ]);
    }

    /**
     * Handle a job failure.
     *
     * Called when all retry attempts are exhausted.
     * Notifies the admin that ZIP generation failed for manual intervention.
     *
     * @param \Throwable $exception The exception that caused the failure
     */
    public function failed(\Throwable $exception): void
    {
        logger()->error("GenerateOrderZipJob FAILED for order {$this->order->reference}", [
            'order_id'  => $this->order->id,
            'error'     => $exception->getMessage(),
            'trace'     => $exception->getTraceAsString(),
        ]);

        // TODO Phase 2: Notify admin via Slack/email about the failure
        // Notification::route('mail', config('mail.admin_address'))
        //     ->notify(new ZipGenerationFailed($this->order, $exception));
    }
}

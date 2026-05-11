<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Command: PurgeExpiredMediaCommand
 *
 * Scheduled command that automatically deletes restored photos from S3
 * after the GDPR-mandated retention period (6 months post-delivery).
 *
 * GDPR Article 5(1)(e) — Storage Limitation:
 *   "Personal data shall be kept in a form which permits identification of
 *    data subjects for no longer than is necessary for the purposes for which
 *    the personal data are processed."
 *
 *   Our retention policy (documented in Privacy Policy):
 *   - Photos (originals + retouched): Deleted 6 months after delivery_at
 *   - Order records (metadata): Kept 5 years (French accounting law — CGI Art. 302)
 *   - Audit logs: Kept 12 months minimum (NIS2 recommendation)
 *
 * Schedule: Run daily at 02:00 AM (configured in routes/console.php)
 * Usage: php artisan media:purge-expired (manual trigger for testing)
 *
 * What gets deleted:
 *   - S3 files in the 'originals' collection
 *   - S3 files in the 'retouched' collection
 *   - S3 files in the 'watermarked' collection
 *   - The ZIP archive (order_deliveries.zip_path)
 *   - All Spatie Media Library records for these collections
 *
 * What is NOT deleted:
 *   - The Order record itself (accounting retention)
 *   - Audit logs (compliance retention)
 *   - User account (managed separately via GDPR erasure flow)
 *
 * @see routes/console.php for scheduling
 */
class PurgeExpiredMediaCommand extends Command
{
    /**
     * The command signature (used to call it from CLI or scheduler).
     * @var string
     */
    protected $signature = 'media:purge-expired
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * Human-readable description shown in: php artisan list
     * @var string
     */
    protected $description = 'Delete restored photos from S3 more than 6 months after delivery (GDPR compliance)';

    /**
     * Execute the command.
     *
     * @return int 0 for success, 1 for failure (used by CI to detect errors)
     */
    public function handle(): int
    {
        $dryRun     = $this->option('dry-run');
        $cutoffDate = now()->subMonths(6);

        $this->info("🗑️  GDPR Media Purge — cutoff date: {$cutoffDate->toDateString()}");

        if ($dryRun) {
            $this->warn('  [DRY RUN] No files will actually be deleted.');
        }

        // Find all orders delivered more than 6 months ago that still have media
        $orders = Order::whereNotNull('delivered_at')
                        ->where('delivered_at', '<', $cutoffDate)
                        ->whereHas('media') // Only orders that still have media attached
                        ->with(['media', 'delivery'])
                        ->get();

        if ($orders->isEmpty()) {
            $this->info('  ✅ No expired media found. Nothing to purge.');
            return self::SUCCESS;
        }

        $this->info("  Found {$orders->count()} order(s) with expired media.");
        $this->newLine();

        $totalDeleted = 0;

        foreach ($orders as $order) {
            $this->line("  → Order {$order->reference} (delivered {$order->delivered_at->toDateString()})");

            if (! $dryRun) {
                // Delete all Spatie Media Library items (removes from S3 + DB records)
                $mediaCount = $order->media->count();
                $order->clearMediaCollection('originals');
                $order->clearMediaCollection('retouched');
                $order->clearMediaCollection('watermarked');

                // Delete the ZIP file from S3 (if it exists)
                if ($order->delivery && $order->delivery->zip_path) {
                    Storage::disk($order->delivery->zip_disk)
                           ->delete($order->delivery->zip_path);

                    $order->delivery->update([
                        'zip_path'  => null,
                        'signed_url'=> null,
                    ]);

                    $this->line("     ✅ Deleted {$mediaCount} media files + ZIP archive");
                } else {
                    $this->line("     ✅ Deleted {$mediaCount} media files (no ZIP found)");
                }

                $totalDeleted++;
            } else {
                // Dry run: just show what would happen
                $mediaCount = $order->media->count();
                $this->line("     [DRY RUN] Would delete {$mediaCount} media files" .
                    ($order->delivery ? ' + ZIP archive' : ''));
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn("  [DRY RUN] Would have purged {$orders->count()} order(s). Run without --dry-run to apply.");
        } else {
            $this->info("  ✅ Successfully purged {$totalDeleted} order(s) of expired media.");
        }

        return self::SUCCESS;
    }
}

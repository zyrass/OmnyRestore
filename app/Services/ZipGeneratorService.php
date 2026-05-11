<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDelivery;
use Illuminate\Support\Facades\Storage;

/**
 * ZipGeneratorService — OmnyRestore
 *
 * Generates the ZIP archive from an order's retouched media files stored on S3.
 * Called by GenerateOrderZipJob (async queue job).
 *
 * Architecture:
 *   1. Fetch all media items from the 'retouched' collection (Spatie Media Library)
 *   2. Download each file from S3 to a temporary local directory
 *   3. Create a ZIP archive using PHP's native ZipArchive class
 *   4. Upload the ZIP to S3 (deliveries bucket)
 *   5. Store the path in order_deliveries table
 *   6. Clean up temporary files
 *
 * Performance considerations:
 *   - This runs in a background queue job — no HTTP timeout concerns
 *   - Large photos (8K TIFF) can be 50MB+ each — memory limit must be configured
 *     (queue worker: PHP memory_limit = 512M minimum for large batches)
 *   - Uses streaming where possible to avoid loading entire files into RAM
 *
 * @see App\Jobs\GenerateOrderZipJob
 * @see App\Models\OrderDelivery
 */
class ZipGeneratorService
{
    /**
     * Generate a ZIP archive for all retouched photos of an order.
     *
     * @param Order $order The completed order to generate a ZIP for
     * @return OrderDelivery The created delivery record with ZIP path
     * @throws \RuntimeException If ZIP creation fails
     */
    public function generate(Order $order): OrderDelivery
    {
        // ─── 1. Prepare temporary directory ───────────────────────────────
        // We need a local workspace to build the ZIP before uploading to S3.
        // PHP's sys_get_temp_dir() returns the OS temp dir (/tmp on Linux, %TEMP% on Windows).
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'omnyrestore_' . $order->id;

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // ─── 2. Fetch retouched media from Spatie Media Library ────────────
        // getMedia('retouched') returns a Collection of Spatie\MediaLibrary\MediaCollections\Models\Media
        $mediaItems = $order->getMedia('retouched');

        if ($mediaItems->isEmpty()) {
            throw new \RuntimeException(
                "Cannot generate ZIP for order {$order->reference}: no retouched photos found."
            );
        }

        // ─── 3. Download each S3 file to temporary directory ──────────────
        $localFiles = [];

        foreach ($mediaItems as $index => $media) {
            // Build a clean local filename: 01_original-filename.jpg
            $localFilename = sprintf('%02d_%s', $index + 1, $media->file_name);
            $localPath     = $tempDir . DIRECTORY_SEPARATOR . $localFilename;

            // Read from S3 via Spatie Media Library's storage path
            // getPath() returns the full path on the disk configured for this collection
            $contents = Storage::disk($media->disk)->get($media->getPath());

            if ($contents === false || $contents === null) {
                throw new \RuntimeException(
                    "Failed to read media file {$media->file_name} from S3 for order {$order->reference}"
                );
            }

            file_put_contents($localPath, $contents);
            $localFiles[] = $localPath;
        }

        // ─── 4. Create the ZIP archive ────────────────────────────────────
        $zipFilename = "{$order->reference}_restauration.zip";
        $zipLocalPath = $tempDir . DIRECTORY_SEPARATOR . $zipFilename;

        $zip = new \ZipArchive();

        // ZipArchive::open() returns true on success, or an error code integer on failure
        $result = $zip->open($zipLocalPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new \RuntimeException(
                "ZipArchive::open() failed for order {$order->reference} with error code: {$result}"
            );
        }

        // Add each downloaded file to the ZIP
        foreach ($localFiles as $localFile) {
            // Second argument: filename INSIDE the ZIP (basename only, no path)
            $zip->addFile($localFile, basename($localFile));
        }

        // Add a README text file inside the ZIP
        $zip->addFromString('README.txt', $this->buildZipReadme($order));

        $zip->close();

        // ─── 5. Upload ZIP to S3 ──────────────────────────────────────────
        $s3Disk = config('filesystems.disks.s3') ? 's3' : 'local';
        $s3Path = "deliveries/{$order->id}/{$zipFilename}";

        $zipContents = file_get_contents($zipLocalPath);
        $uploaded    = Storage::disk($s3Disk)->put($s3Path, $zipContents);

        if (! $uploaded) {
            throw new \RuntimeException(
                "Failed to upload ZIP to S3 for order {$order->reference}"
            );
        }

        $zipSize = strlen($zipContents);

        // ─── 6. Clean up temporary files ─────────────────────────────────
        $this->cleanupTempDir($tempDir);

        // ─── 7. Create or update the delivery record ──────────────────────
        $delivery = OrderDelivery::updateOrCreate(
            ['order_id' => $order->id],
            [
                'zip_disk' => $s3Disk,
                'zip_path' => $s3Path,
                'zip_size' => $zipSize,
            ]
        );

        return $delivery;
    }

    /**
     * Build the README.txt file included inside the ZIP.
     * Provides context to the client about the restoration work.
     */
    private function buildZipReadme(Order $order): string
    {
        return implode(PHP_EOL, [
            "=== OmnyRestore — Commande {$order->reference} ===",
            "",
            "Photos restaurées par intelligence artificielle.",
            "Technologie : Restauration 8K, optimisation Studio Professionnel.",
            "",
            "Contenu de cette archive :",
            "  - {$order->photo_count} photo(s) restaurée(s) en haute résolution",
            "  - README.txt (ce fichier)",
            "",
            "Date de livraison : " . ($order->delivered_at?->format('d/m/Y H:i') ?? 'N/A'),
            "Référence commande : {$order->reference}",
            "",
            "© OmnyRestore — OmnyVia",
            "https://omnyrestore.fr",
        ]);
    }

    /**
     * Recursively delete the temporary working directory.
     */
    private function cleanupTempDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->cleanupTempDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}

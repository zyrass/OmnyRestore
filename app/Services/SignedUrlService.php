<?php

namespace App\Services;

use App\Models\OrderDelivery;
use Illuminate\Support\Facades\Storage;

/**
 * SignedUrlService — OmnyRestore
 *
 * Generates AWS S3 pre-signed URLs for secure ZIP file downloads.
 *
 * What is a pre-signed URL?
 *   A pre-signed URL is a temporary URL that grants time-limited access to
 *   a private S3 object WITHOUT exposing AWS credentials or making the object public.
 *   AWS embeds authentication directly in the URL query string (signature, expiry, key).
 *
 * Security model:
 *   - The S3 bucket is PRIVATE (no public access at all)
 *   - Only this service can generate access URLs (via AWS SDK)
 *   - URLs expire after 48 hours (configurable)
 *   - Even if a URL leaks, it stops working after TTL
 *   - Laravel still checks auth + payment before generating the URL (defense in depth)
 *
 * @see App\Jobs\GenerateSignedDownloadUrlJob
 * @see App\Models\OrderDelivery
 * @see https://docs.aws.amazon.com/AmazonS3/latest/userguide/ShareObjectPreSignedURL.html
 */
class SignedUrlService
{
    /**
     * TTL (Time To Live) for presigned URLs in hours.
     * 48 hours gives the client ample time to download.
     */
    private const TTL_HOURS = 48;

    /**
     * Generate or refresh a pre-signed URL for a delivery's ZIP file.
     *
     * If the delivery already has a valid URL (not expired), it is returned as-is.
     * Otherwise, a fresh URL is generated and stored on the delivery record.
     *
     * @param OrderDelivery $delivery The delivery record containing the ZIP path
     * @return string The pre-signed URL (valid for TTL_HOURS hours)
     * @throws \RuntimeException If URL generation fails
     */
    public function getOrGenerate(OrderDelivery $delivery): string
    {
        // If the cached URL is still valid, return it immediately
        // (avoids unnecessary AWS API calls on every page load)
        if ($delivery->hasValidSignedUrl()) {
            return $delivery->signed_url;
        }

        // Generate a fresh pre-signed URL
        $url = $this->generate($delivery);

        // Cache the new URL on the delivery record
        $delivery->updateSignedUrl($url);

        return $url;
    }

    /**
     * Generate a fresh AWS S3 pre-signed URL.
     *
     * Uses Laravel's Storage facade which wraps the AWS SDK.
     * The temporaryUrl() method calls S3::getObjectRequest() + signs it with credentials.
     *
     * @param OrderDelivery $delivery
     * @return string The pre-signed URL
     * @throws \RuntimeException If the disk doesn't support signed URLs (e.g., local disk)
     */
    public function generate(OrderDelivery $delivery): string
    {
        $disk    = $delivery->zip_disk;
        $path    = $delivery->zip_path;
        $expiry  = now()->addHours(self::TTL_HOURS);

        // ── Disk local (développement) ──────────────────────────────────────
        // En local, pas d'URL S3 pré-signée. On retourne une URL Laravel signée
        // qui pointe vers la route de téléchargement direct.
        if ($disk === 'local') {
            return \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'client.orders.download.stream',
                $expiry,
                ['delivery' => $delivery->id]
            );
        }

        // ── Disk S3 (production) ────────────────────────────────────────────
        try {
            $url = Storage::disk($disk)->temporaryUrl(
                $path,
                $expiry,
                [
                    'ResponseContentDisposition' => 'attachment; filename="' . basename($path) . '"',
                    'ResponseContentType' => 'application/zip',
                ]
            );
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to generate pre-signed URL for delivery {$delivery->id}: " . $e->getMessage()
            );
        }

        return $url;
    }
}

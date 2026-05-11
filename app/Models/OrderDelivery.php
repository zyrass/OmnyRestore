<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * OrderDelivery Model — OmnyRestore
 *
 * Represents the ZIP archive delivery for a completed order.
 * Created by GenerateOrderZipJob after admin marks order as DONE.
 *
 * Security: ZIP is in a PRIVATE S3 bucket. Clients never get a direct S3 URL.
 * All downloads route through Laravel which verifies auth + payment + ownership.
 *
 * @property string $id UUID primary key
 * @property string $order_id FK to orders
 * @property string $zip_disk Storage disk name ('s3' | 'local')
 * @property string $zip_path Path within the disk
 * @property int|null $zip_size File size in bytes
 * @property string|null $signed_url AWS pre-signed URL (TTL: 48h)
 * @property \Carbon\Carbon|null $signed_url_expires_at
 * @property int $download_count
 * @property \Carbon\Carbon|null $last_downloaded_at
 */
class OrderDelivery extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'order_id',
        'zip_disk',
        'zip_path',
        'zip_size',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'zip_size'              => 'integer',
            'download_count'        => 'integer',
            'signed_url_expires_at' => 'datetime',
            'last_downloaded_at'    => 'datetime',
        ];
    }

    // =========================================================================
    // SIGNED URL MANAGEMENT
    // =========================================================================

    /**
     * Check if the current signed URL is still valid.
     * Buffer of 5 minutes to avoid edge cases where URL expires mid-download.
     */
    public function hasValidSignedUrl(): bool
    {
        if (! $this->signed_url || ! $this->signed_url_expires_at) {
            return false;
        }
        return $this->signed_url_expires_at->isAfter(now()->addMinutes(5));
    }

    /**
     * Update the signed URL after generation.
     * Called by SignedUrlService after creating a new AWS presigned URL.
     * TTL is 48 hours by convention.
     */
    public function updateSignedUrl(string $url): void
    {
        $this->signed_url            = $url;
        $this->signed_url_expires_at = now()->addHours(48);
        $this->save();
    }

    /**
     * Record a download event: increment counter + timestamp.
     * Called by OrderDownloadController before redirecting to signed URL.
     */
    public function recordDownload(): void
    {
        $this->increment('download_count');
        $this->last_downloaded_at = now();
        $this->save();
    }

    /**
     * Human-readable ZIP file size (e.g., "43.56 MB").
     */
    public function getHumanSizeAttribute(): string
    {
        if (! $this->zip_size) {
            return 'Unknown';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->zip_size;
        $i     = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}

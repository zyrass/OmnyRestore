<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AuditService;
use App\Services\SignedUrlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller: OrderDownloadController (Client)
 *
 * Handles the secure download of restored photo ZIP archives.
 *
 * Security architecture:
 *   This controller is the ONLY entry point for downloading ZIP files.
 *   The S3 bucket is private — no direct S3 URLs are ever exposed to clients.
 *
 *   Before serving a download, we verify:
 *   1. User is authenticated (auth middleware)
 *   2. User's email is verified (verified middleware)
 *   3. User OWNS this specific order (OrderPolicy::download via authorize())
 *   4. Order's payment_status is 'paid' (inside OrderPolicy::download)
 *   5. An OrderDelivery record exists with a ZIP path (inside OrderPolicy::download)
 *
 *   If all checks pass → generate/refresh a 48h presigned S3 URL → 302 redirect
 *   S3 serves the file directly — Laravel does NOT proxy the download
 *
 * Route: GET /client/orders/{order}/download
 *   Middleware: ['auth', 'verified']
 *   Policy: OrderPolicy::download
 */
class OrderDownloadController extends Controller
{
    public function __construct(
        private readonly SignedUrlService $signedUrlService,
        private readonly AuditService    $auditService
    ) {}

    /**
     * Generate a presigned download URL and redirect the client to it.
     *
     * @param Request $request  The HTTP request (used for audit logging)
     * @param Order   $order    Route model binding — Laravel fetches this automatically
     * @return RedirectResponse 302 redirect to the S3 presigned URL
     */
    public function download(Request $request, Order $order): RedirectResponse
    {
        // Gate check: throws 403 if user doesn't own the order or hasn't paid
        // This calls OrderPolicy::download($user, $order)
        $this->authorize('download', $order);

        $delivery = $order->delivery;

        // Generate or refresh the presigned URL (cached for 48h, auto-renewed when expired)
        $downloadUrl = $this->signedUrlService->getOrGenerate($delivery);

        // Record the download in the audit log (GDPR compliance)
        $this->auditService->downloadInitiated($order);

        // Increment download counter and update last_downloaded_at
        $delivery->recordDownload();

        // Redirect the client to the S3 presigned URL.
        // The actual file download happens between the client and S3 — no Laravel proxy.
        // This keeps our server load minimal even for large files.
        return redirect()->away($downloadUrl);
    }
}

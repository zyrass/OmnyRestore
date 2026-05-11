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
        // 1. Vérification ownership (IDOR prevention)
        abort_if($order->user_id !== $request->user()->id, 403, 'Accès non autorisé.');

        // 2. Vérification paiement — seules les commandes payées permettent le téléchargement
        abort_if($order->payment_status !== 'paid', 403, 'Cette commande n\'a pas encore été payée.');

        // 3. Vérification existence du ZIP
        $delivery = $order->delivery;
        abort_if(! $delivery || ! $delivery->zip_path, 404, 'Archive non disponible — génération en cours.');

        // Générer ou rafraîchir l'URL signée (cache 48h, auto-renouvellement)
        $downloadUrl = $this->signedUrlService->getOrGenerate($delivery);

        // Log audit RGPD
        $this->auditService->downloadInitiated($order);

        // Incrémenter le compteur de téléchargements
        $delivery->recordDownload();

        // Redirection 302 vers l'URL signée
        // Le fichier est servi directement par S3 (ou PHP en local) — pas de proxy Laravel
        return redirect()->away($downloadUrl);
    }
}

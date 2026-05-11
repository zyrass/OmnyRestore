<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Controller: OrderDownloadController (Client)
 *
 * Sert le ZIP de photos restaurées après vérification du paiement.
 * En local  : response()->download() depuis storage/app/
 * En prod   : redirect vers URL AWS S3 pré-signée (48h)
 *
 * Route: GET /client/orders/{order}/download
 *   Middleware: ['auth', 'verified']
 */
class OrderDownloadController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    public function download(Request $request, Order $order): \Symfony\Component\HttpFoundation\Response
    {
        // 1. Vérification ownership (IDOR prevention)
        abort_if($order->user_id !== $request->user()->id, 403, 'Accès non autorisé.');

        // 2. Vérification paiement
        abort_if($order->payment_status !== 'paid', 403, 'Cette commande n\'a pas encore été payée.');

        // 3. Vérification existence du ZIP
        $zipPath = $order->zip_path;
        abort_if(! $zipPath, 404, 'Archive non disponible — génération en cours.');

        // Log audit RGPD
        $this->auditService->downloadInitiated($order);

        // ── Disk local (développement) ──────────────────────────────────────
        $disk = config('filesystems.default', 'local');
        if ($disk === 'local') {
            $absolutePath = storage_path('app/' . $zipPath);
            abort_unless(file_exists($absolutePath), 404, 'Fichier ZIP introuvable sur le disque.');

            return response()->download(
                $absolutePath,
                'OmnyRestore_' . $order->reference . '.zip',
                ['Content-Type' => 'application/zip']
            );
        }

        // ── Disk S3 (production) ────────────────────────────────────────────
        $url = Storage::disk($disk)->temporaryUrl(
            $zipPath,
            now()->addHours(48),
            [
                'ResponseContentDisposition' => 'attachment; filename="OmnyRestore_' . $order->reference . '.zip"',
                'ResponseContentType' => 'application/zip',
            ]
        );

        return redirect()->away($url);
    }
}

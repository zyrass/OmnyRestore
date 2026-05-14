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

        // Enregistrement du téléchargement pour le support (refonte remboursement)
        $delivery = $order->delivery;
        if (! $delivery) {
            $delivery = $order->delivery()->create([
                'zip_disk' => config('filesystems.default', 'local'),
                'zip_path' => $order->zip_path,
                'zip_size' => 0, // Fallback safe, on ne veut pas bloquer le téléchargement pour la taille
            ]);
        }
        
        $delivery->recordDownload();

        // 4. Notification Support (Anti-remboursement)
        // Si un ticket est ouvert pour cette commande, on ajoute un message système.
        $ticket = \App\Models\SupportTicket::where('order_id', $order->id)
            ->where('status', '!=', 'closed')
            ->first();

        if ($ticket) {
            $ticket->messages()->create([
                'user_id' => null, // Message système
                'body'    => "⚠️ **NOTIFICATION SYSTÈME** : Le client a téléchargé l'archive ZIP des photos retouchées le " . now()->format('d/m/Y à H:i') . ". Conformément à nos CGV, la prestation est considérée comme exécutée et plus aucun remboursement n'est possible pour cette commande.",
                'is_admin' => true,
                'is_read'  => false,
            ]);
        }

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

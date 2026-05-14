<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * AdminInvoiceController — Génération de facture PDF pour l'admin
 *
 * Permet à l'admin de télécharger la facture, même si le compte client
 * a été supprimé ou anonymisé (RGPD).
 *
 * Route: GET /admin/orders/{order}/invoice
 */
class AdminInvoiceController extends Controller
{
    public function download(Order $order): Response
    {
        // La facture n'existe techniquement que si la commande a été payée
        abort_if($order->payment_status !== 'paid' && $order->payment_status !== 'refunded', 403, 'La facture n\'est disponible qu\'après paiement.');

        // Charger l'utilisateur, même s'il est soft deleted / anonymisé
        $order->load(['user' => fn($q) => $q->withTrashed()]);

        $pdf = Pdf::loadView('pdf.invoice', compact('order'))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'defaultFont'           => 'helvetica',
                'isHtml5ParserEnabled'  => true,
                'isRemoteEnabled'       => false,
                'isFontSubsettingEnabled' => true,
                'defaultMediaType'      => 'print',
                'dpi'                   => 150,
                'enable_php'            => false,
            ]);

        $filename = 'facture-omnyrestore-' . $order->reference . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}

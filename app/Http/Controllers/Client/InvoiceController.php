<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * InvoiceController — Génération de facture PDF client
 *
 * Route: GET /client/orders/{order}/invoice
 * Middleware: auth, verified, client (ou owner)
 */
class InvoiceController extends Controller
{
    public function download(Order $order): Response
    {
        // Vérifier que l'order appartient au client connecté
        abort_if($order->user_id !== Auth::id(), 403, 'Accès non autorisé.');

        // Vérifier que la commande est payée
        abort_if($order->payment_status !== 'paid', 403, 'La facture n\'est disponible qu\'après paiement.');

        $order->load('user');

        $pdf = Pdf::loadView('pdf.invoice', compact('order'))
            ->setPaper('A4')
            ->setOptions([
                'defaultFont' => 'helvetica',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'dpi' => 150,
            ]);

        $filename = 'facture-' . $order->reference . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}

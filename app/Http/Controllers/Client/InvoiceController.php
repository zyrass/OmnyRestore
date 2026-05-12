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
 * Middleware: auth, verified
 */
class InvoiceController extends Controller
{
    public function download(Order $order): Response
    {
        // Vérifier que l'order appartient au client connecté
        abort_if($order->user_id !== Auth::id(), 403, 'Accès non autorisé.');

        // Vérifier que la commande est payée
        abort_if($order->payment_status !== 'paid', 403, 'La facture n\'est disponible qu\'après paiement.');

        // Charger les relations nécessaires au template
        $order->load('user');

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

        // inline = s'ouvre dans le navigateur (téléchargeable via le bouton du PDF viewer)
        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}

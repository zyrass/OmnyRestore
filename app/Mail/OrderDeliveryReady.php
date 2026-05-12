<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email: OrderDeliveryReady
 *
 * Envoyé au client quand le GenerateOrderZipJob a terminé avec succès.
 * Contient :
 *   - Le lien de téléchargement du ZIP (sécurisé)
 *   - Le lien de téléchargement de la facture PDF
 *
 * Déclencheur: OrderObserver → status 'DELIVERED'
 * (après que GenerateOrderZipJob ait créé le ZIP et mis à jour la commande)
 *
 * @see App\Jobs\GenerateOrderZipJob
 * @see App\Observers\OrderObserver
 */
class OrderDeliveryReady extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⬇ Vos photos sont prêtes à télécharger — {$this->order->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.delivery-ready',
        );
    }
}

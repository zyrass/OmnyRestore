<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email: OrderPaymentFailed
 *
 * Envoyé au client quand son paiement Stripe est refusé.
 * Déclenché par le webhook payment_intent.payment_failed.
 *
 * Contenu :
 *   - Explication claire et bienveillante du refus
 *   - Causes possibles (fonds insuffisants, carte expirée, 3DS)
 *   - CTA vers la page commande pour réessayer
 *   - Pas de mention du montant précis (la commande peut avoir changé)
 */
class OrderPaymentFailed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly ?string $failureReason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⚠️ Paiement refusé — Commande {$this->order->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.payment-failed',
        );
    }
}

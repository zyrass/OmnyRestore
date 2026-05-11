<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email: OrderPaidConfirmation
 *
 * Envoyé au client après confirmation du paiement Stripe (webhook).
 * Confirme le paiement et indique que le téléchargement ZIP est disponible.
 */
class OrderPaidConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "✅ Paiement confirmé — Téléchargez vos photos — {$this->order->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.paid-confirmation',
        );
    }
}

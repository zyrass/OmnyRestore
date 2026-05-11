<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email: OrderReadyForPayment
 *
 * Envoyé au client quand l'admin marque sa commande comme DONE.
 * Contient un lien vers la page de détail avec les aperçus filigranés
 * et le bouton de paiement Stripe.
 *
 * Déclencheur: Order::markAsDone() → OrderObserver → Mail::to()->queue()
 */
class OrderReadyForPayment extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "✨ Vos photos restaurées sont prêtes — {$this->order->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.ready-for-payment',
        );
    }
}

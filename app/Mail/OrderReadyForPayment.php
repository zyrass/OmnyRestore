<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Email: OrderReadyForPayment
 *
 * Envoyé au client quand l'admin marque sa commande comme DONE.
 * Contient un lien SIGNÉ (temporarySignedRoute, 7 jours) vers la route
 * d'unlock-preview, qui positionne preview_unlocked_at avant de rediriger
 * vers la page commande. Cela garantit que le client a lu l'email avant
 * de pouvoir accéder aux aperçus filigranés.
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
        // URL signée — expire dans 7 jours.
        // Elle pointe vers UnlockPreviewController qui positionne preview_unlocked_at
        // puis redirige vers la page commande (auth middleware gère la connexion si besoin).
        $signedUrl = URL::temporarySignedRoute(
            'client.orders.unlock-preview',
            now()->addDays(7),
            ['order' => $this->order->id],
        );

        return new Content(
            view: 'emails.orders.ready-for-payment',
            with: ['signedUrl' => $signedUrl],
        );
    }
}

<?php

namespace App\Observers;

use App\Mail\OrderDeliveryReady;
use App\Mail\OrderPaidConfirmation;
use App\Mail\OrderReadyForPayment;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * OrderObserver — Réagit aux changements de statut d'une commande
 *
 * Enregistré dans AppServiceProvider::boot() via Order::observe(OrderObserver::class).
 *
 * Événements gérés :
 *   - updated : Détecte les transitions de statut et envoie les emails appropriés
 *
 * Emails envoyés :
 *   DONE  → OrderReadyForPayment  (aperçus prêts, lien de paiement)
 *   PAID  → OrderPaidConfirmation (confirmation + lien téléchargement ZIP)
 *
 * Les emails sont mis en file d'attente (queue) pour ne pas bloquer la réponse HTTP.
 * Config: QUEUE_CONNECTION=database (ou redis en production)
 */
class OrderObserver
{
    /**
     * Déclenché après chaque Order::save() / Order::update().
     * On compare le statut précédent (getOriginal) avec le nouveau.
     */
    public function updated(Order $order): void
    {
        // Vérifier si le statut a changé dans cette mise à jour
        if (! $order->wasChanged('status')) {
            return;
        }

        $newStatus  = $order->status;
        $userEmail  = $order->user->email;
        $userName   = $order->user->name;

        Log::info("OrderObserver: status changed → {$newStatus}", [
            'order_id'  => $order->id,
            'reference' => $order->reference,
            'user'      => $userEmail,
        ]);

        try {
            match ($newStatus) {
                // L'admin vient de finir la restauration → Le mail sera déclenché MANUELLEMENT
                // via le bouton dans le panel admin (resendClientNotification).
                'DONE' => null,

                // Stripe a confirmé le paiement → email "paiement reçu, ZIP en préparation"
                // ⚠️ Le GenerateOrderZipJob est dispatched par PaymentSuccessController
                //    ET StripeWebhookController — pas ici pour éviter le double dispatch.
                'PAID' => Mail::to($userEmail, $userName)
                              ->queue(new OrderPaidConfirmation($order)),

                // Le statut DELIVERED est atteint manuellement via l'admin qui envoie l'email
                // de livraison (ZIP + facture). Le mail est déclenché dans sendDeliveryEmail().
                'DELIVERED' => null,

                // Pas d'email pour les autres transitions
                default => null,
            };

            if (in_array($newStatus, ['DONE', 'PAID', 'DELIVERED'])) {
                Log::info("OrderObserver: email {$newStatus} queued → {$userEmail}", [
                    'reference' => $order->reference,
                    'status'    => $newStatus,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("OrderObserver: échec envoi email {$newStatus} → {$userEmail}", [
                'error'     => $e->getMessage(),
                'reference' => $order->reference,
            ]);
        }
    }
}

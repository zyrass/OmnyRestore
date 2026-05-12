<?php

namespace App\Observers;

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
                // L'admin vient de finir la restauration → notifier le client
                'DONE' => Mail::to($userEmail, $userName)
                              ->queue(new OrderReadyForPayment($order)),

                // Stripe a confirmé le paiement → email confirmation client
                // ⚠️ Le GenerateOrderZipJob est dispatchED par PaymentSuccessController
                //    ET StripeWebhookController — pas ici pour éviter le double dispatch.
                'PAID' => Mail::to($userEmail, $userName)
                              ->queue(new OrderPaidConfirmation($order)),

                // Pas d'email pour les autres transitions (PAID→DELIVERED, etc.)
                default => null,
            };

            if (in_array($newStatus, ['DONE', 'PAID'])) {
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

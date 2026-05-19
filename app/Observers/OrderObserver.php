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
                // L'admin vient de finir la restauration
                'DONE' => Mail::to($userEmail)->send(new \App\Mail\OrderReadyForPayment($order)),

                // Paiement réussi (automatique via Webhook)
                'PAID' => Mail::to($userEmail)->send(new \App\Mail\OrderPaidConfirmation($order)),

                // Le statut DELIVERED est atteint quand le ZIP est prêt (Job terminé)
                'DELIVERED' => Mail::to($userEmail)->send(new \App\Mail\OrderDeliveryReady($order)),

                default => null,
            };

            if (in_array($newStatus, ['DONE', 'PAID', 'DELIVERED'])) {
                Log::info("OrderObserver: email {$newStatus} queued → {$userEmail}", [
                    'reference' => $order->reference,
                    'status'    => $newStatus,
                ]);
            }

            // Déclencher le système de fidélisation si la commande est payée/livrée
            if (in_array($newStatus, ['PAID', 'DELIVERED'])) {
                try {
                    app(\App\Services\LoyaltyService::class)->checkAndReward($order->user);
                } catch (\Throwable $e) {
                    Log::error("OrderObserver: échec de vérification fidélité pour {$userEmail}", [
                        'error' => $e->getMessage(),
                        'user_id' => $order->user_id,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error("OrderObserver: échec envoi email {$newStatus} → {$userEmail}", [
                'error'     => $e->getMessage(),
                'reference' => $order->reference,
            ]);
        }
    }
}

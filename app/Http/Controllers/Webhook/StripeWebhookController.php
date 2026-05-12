<?php

namespace App\Http\Controllers\Webhook;

use App\Jobs\GenerateOrderZipJob;
use App\Models\Order;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;

/**
 * StripeWebhookController — Reçoit et traite les événements Stripe
 *
 * Route: POST /webhook/stripe (CSRF exempt — vérifié par signature HMAC)
 *
 * Événements traités :
 *   checkout.session.completed → marque la commande comme PAID
 *   payment_intent.payment_failed → log l'échec (notification admin future)
 *
 * Sécurité :
 *   La signature Stripe (STRIPE_WEBHOOK_SECRET) est vérifiée AVANT tout traitement.
 *   Un webhook sans signature valide retourne 400 immédiatement.
 *   Idempotence : on vérifie que payment_status !== 'paid' avant de traiter.
 *
 * @see https://stripe.com/docs/webhooks
 * @see https://stripe.com/docs/api/events/types
 */
class StripeWebhookController
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Point d'entrée unique pour tous les webhooks Stripe.
     * Stripe envoie chaque événement en JSON via POST.
     */
    public function handleWebhook(Request $request): Response
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('cashier.webhook.secret') ?: env('STRIPE_WEBHOOK_SECRET');

        // ── Vérification de la signature HMAC ──────────────────────────────
        try {
            Stripe::setApiKey(config('cashier.secret') ?: env('STRIPE_SECRET'));
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook: invalid payload', ['error' => $e->getMessage()]);
            return response('Invalid payload', 400);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook: invalid signature', ['error' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        Log::info("Stripe webhook received: {$event->type}", ['event_id' => $event->id]);

        // ── Dispatch selon le type d'événement ─────────────────────────────
        match ($event->type) {
            'checkout.session.completed'    => $this->handleCheckoutCompleted($event),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event),
            default => null, // Ignorer silencieusement les autres types
        };

        // Toujours retourner 200 pour que Stripe ne réessaie pas
        return response('Webhook handled', 200);
    }

    /**
     * checkout.session.completed
     *
     * Déclenché quand le client finalise le paiement sur la page Stripe.
     * Cherche la commande via les métadonnées et la marque PAID.
     */
    private function handleCheckoutCompleted(Event $event): void
    {
        $session = $event->data->object;

        // Récupérer l'order_id depuis les métadonnées de la session Stripe
        $orderId = $session->metadata->order_id ?? null;
        if (! $orderId) {
            Log::error('Stripe webhook: checkout.session.completed missing order_id metadata');
            return;
        }

        $order = Order::find($orderId);
        if (! $order) {
            Log::error("Stripe webhook: Order not found for id={$orderId}");
            return;
        }

        // Idempotence : ne pas marquer PAID deux fois (Stripe peut renvoyer)
        if ($order->payment_status === 'paid') {
            Log::info("Stripe webhook: Order {$order->reference} already paid — skipping");
            return;
        }

        // Marquer la commande comme payée
        $order->update([
            'status'         => 'PAID',
            'payment_status' => 'paid',
            'paid_at'        => now(),
        ]);

        $this->audit->orderStatusChanged($order, $order->getOriginal('status') ?? 'DONE', 'PAID');

        // ── Déclencher la génération du ZIP en background ──────────────────
        // L'email OrderPaidConfirmation est envoyé par l'OrderObserver (status→PAID)
        // pour éviter tout double envoi. Le webhook est la seule source de vérité
        // pour le dispatch du job ZIP.
        GenerateOrderZipJob::dispatch($order)->onQueue('default');

        Log::info("Stripe webhook: Order {$order->reference} PAID — ZIP job dispatched, email via Observer");
    }


    /**
     * payment_intent.payment_failed
     *
     * Le client a tenté de payer mais sa carte a été refusée.
     * On log l'événement pour suivi admin — pas de changement de statut.
     */
    private function handlePaymentFailed(Event $event): void
    {
        $intent  = $event->data->object;
        $orderId = $intent->metadata->order_id ?? 'unknown';

        Log::warning("Stripe webhook: payment failed for order={$orderId}", [
            'failure_message' => $intent->last_payment_error?->message ?? 'Unknown',
            'failure_code'    => $intent->last_payment_error?->code ?? 'unknown',
        ]);
    }
}

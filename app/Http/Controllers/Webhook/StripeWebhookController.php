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

        // Marquer la commande comme payée via la méthode de la machine d'état.
        // N'utilise PAS $order->update([...]) car status et payment_status ne sont
        // plus dans $fillable — ils ne peuvent être modifiés que via les méthodes dédiées.
        $order->markAsPaid($session->payment_intent ?? 'cs_' . $session->id);
        $order->forceFill(['status' => 'PAID'])->save();

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
     * On envoie un email de relance bienveillant avec les causes possibles
     * et un lien pour réessayer le paiement.
     *
     * Recherche de l'ordre :
     *   1. metadata.order_id (disponible si payment_intent_data.metadata est défini)
     *   2. Fallback : orders.payment_intent_id (toujours renseigné au moment du checkout)
     */
    private function handlePaymentFailed(Event $event): void
    {
        $intent  = $event->data->object;
        $orderId = $intent->metadata->order_id ?? null;

        // Recherche par metadata d'abord, puis par payment_intent_id en fallback
        $order = $orderId
            ? Order::find($orderId)
            : Order::where('payment_intent_id', $intent->id)->first();

        $failureMessage = $intent->last_payment_error?->message ?? null;
        $failureCode    = $intent->last_payment_error?->code ?? 'unknown';

        Log::warning("Stripe webhook: payment failed", [
            'order_id'        => $orderId ?? 'unknown',
            'intent_id'       => $intent->id,
            'failure_code'    => $failureCode,
            'failure_message' => $failureMessage,
        ]);

        if (! $order) {
            Log::error("Stripe webhook: payment_failed — commande introuvable", [
                'order_id'  => $orderId,
                'intent_id' => $intent->id,
            ]);
            return;
        }

        // Envoi asynchrone pour ne pas bloquer la réponse 200 à Stripe
        try {
            \Illuminate\Support\Facades\Mail::to($order->user->email)
                ->queue(new \App\Mail\OrderPaymentFailed($order, $failureMessage));

            Log::info("Stripe webhook: email OrderPaymentFailed envoyé pour {$order->reference}");
        } catch (\Throwable $e) {
            Log::error("Stripe webhook: échec envoi OrderPaymentFailed pour {$order->reference}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

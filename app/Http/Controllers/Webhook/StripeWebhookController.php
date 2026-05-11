<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AuditService;
use App\Jobs\GenerateSignedDownloadUrlJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

/**
 * Controller: StripeWebhookController
 *
 * Handles incoming webhook events from Stripe.
 * This is the ONLY place where payment status is updated in our database.
 *
 * Security (CRITICAL):
 *   Stripe sends webhooks to a public endpoint WITHOUT authentication.
 *   Anyone could send a fake POST to /webhook/stripe claiming a payment succeeded.
 *   To prevent this, we MUST verify the HMAC-SHA256 signature on every request.
 *
 *   Laravel Cashier handles this automatically when you extend CashierWebhookController.
 *   The STRIPE_WEBHOOK_SECRET in .env is used to verify the signature.
 *   If verification fails → Cashier returns 401/403 before our code runs.
 *
 * CSRF Exemption:
 *   Stripe cannot provide a CSRF token. The webhook route must be excluded
 *   from CSRF protection in bootstrap/app.php:
 *     $middleware->validateCsrfTokens(except: ['/webhook/stripe']);
 *
 * Route: POST /webhook/stripe
 *   Middleware: CSRF excluded only — no auth/verified (Stripe is the caller)
 *
 * Testing webhooks locally:
 *   stripe listen --forward-to localhost:8000/webhook/stripe
 *   stripe trigger payment_intent.succeeded
 *
 * @see https://laravel.com/docs/billing#handling-stripe-webhooks
 */
class StripeWebhookController extends CashierWebhookController
{
    /**
     * Handle a successful payment intent.
     *
     * Triggered by Stripe event: payment_intent.succeeded
     * This fires when a Stripe Checkout Session payment is confirmed.
     *
     * What we do here:
     *   1. Find the order by payment_intent_id
     *   2. Mark it as paid (updates payment_status + paid_at)
     *   3. Generate a presigned download URL (async job)
     *   4. Write to audit log
     *
     * @param array $payload The full Stripe event payload (already verified by Cashier)
     */
    public function handlePaymentIntentSucceeded(array $payload): Response
    {
        $paymentIntentId = $payload['data']['object']['id'];
        $amountReceived  = $payload['data']['object']['amount_received']; // In cents

        // Find the order with this Stripe PaymentIntent ID
        $order = Order::where('payment_intent_id', $paymentIntentId)->first();

        if (! $order) {
            // Stripe can send events for test payments or other platform orders
            // Log and return 200 (Stripe will retry on non-2xx response)
            logger()->warning('StripeWebhook: payment_intent.succeeded — no matching order', [
                'payment_intent_id' => $paymentIntentId,
            ]);
            return $this->successMethod();
        }

        // Idempotency check: don't process the same payment twice
        // (Stripe may retry webhook delivery on network issues)
        if ($order->payment_status === 'paid') {
            logger()->info('StripeWebhook: payment_intent.succeeded — already processed (idempotent)', [
                'order_reference' => $order->reference,
            ]);
            return $this->successMethod();
        }

        // Mark the order as paid
        $order->markAsPaid($paymentIntentId);

        // Dispatch job to generate presigned download URL and send email
        GenerateSignedDownloadUrlJob::dispatch($order);

        // Write to audit log (no user_id — this is a system/Stripe event)
        app(AuditService::class)->paymentSucceeded($order, $paymentIntentId, $amountReceived);

        logger()->info('StripeWebhook: payment_intent.succeeded — order marked as paid', [
            'order_reference'   => $order->reference,
            'payment_intent_id' => $paymentIntentId,
            'amount_cents'      => $amountReceived,
        ]);

        // Return 200 to tell Stripe the webhook was processed successfully.
        // If we return non-200, Stripe will retry for up to 72 hours.
        return $this->successMethod();
    }

    /**
     * Handle a failed payment intent.
     *
     * Triggered by Stripe event: payment_intent.payment_failed
     * The client's card was declined or 3DS authentication failed.
     */
    public function handlePaymentIntentPaymentFailed(array $payload): Response
    {
        $paymentIntentId = $payload['data']['object']['id'];

        $order = Order::where('payment_intent_id', $paymentIntentId)->first();

        if ($order) {
            $order->payment_status = 'failed';
            $order->save();

            // TODO: Notify client via email that their payment failed
            // $order->user->notify(new PaymentFailed($order));

            logger()->warning('StripeWebhook: payment_intent.payment_failed', [
                'order_reference'   => $order->reference,
                'payment_intent_id' => $paymentIntentId,
            ]);
        }

        return $this->successMethod();
    }
}

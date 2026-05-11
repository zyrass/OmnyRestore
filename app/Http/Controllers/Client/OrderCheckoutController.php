<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller: OrderCheckoutController (Client)
 *
 * Initiates a Stripe Checkout Session for an order.
 *
 * Flow:
 *   1. Client clicks "Payer" on the order detail page
 *   2. Laravel creates a Stripe Checkout Session (server-side)
 *   3. Client is redirected to Stripe's hosted payment page
 *   4. After payment: Stripe redirects to /payment/success or /payment/cancel
 *   5. Stripe webhook (payment_intent.succeeded) confirms and marks order as paid
 *
 * Note: Payment confirmation NEVER relies on the success redirect URL.
 * Only the webhook is authoritative. The success page just shows a friendly message.
 *
 * @see App\Http\Controllers\Webhook\StripeWebhookController
 * @see https://stripe.com/docs/checkout/quickstart
 *
 * TODO Phase 2: Implement full Stripe Checkout Session creation
 */
class OrderCheckoutController extends Controller
{
    /**
     * Create a Stripe Checkout Session and redirect the client to it.
     *
     * Authorization: OrderPolicy handles IDOR prevention.
     * Only the order owner can initiate payment, and only for DONE orders.
     *
     * @param Request $request
     * @param Order   $order Route model binding
     * @return RedirectResponse Redirect to Stripe Checkout (external) or back with error
     */
    public function checkout(Request $request, Order $order): RedirectResponse
    {
        // Verify the user owns this order and it's in a payable state
        $this->authorize('view', $order);

        // Safety check: only allow checkout for DONE orders awaiting payment
        if (! $order->awaitingPayment()) {
            return back()->with('error', 'Cette commande n\'est pas disponible au paiement.');
        }

        // TODO Phase 2: Create Stripe Checkout Session
        // $session = $order->user->checkout([
        //     ['price_data' => [...], 'quantity' => 1],
        // ], [
        //     'success_url' => route('payment.success') . '?session_id={CHECKOUT_SESSION_ID}',
        //     'cancel_url'  => route('payment.cancel'),
        //     'metadata'    => ['order_id' => $order->id],
        // ]);
        // return redirect($session->url);

        // Placeholder until Stripe is configured
        return back()->with('info', 'Paiement Stripe — implémentation Phase 2.');
    }
}

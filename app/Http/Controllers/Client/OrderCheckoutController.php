<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Stripe;

/**
 * OrderCheckoutController — Initie le paiement Stripe Checkout
 *
 * Flow complet :
 *   1. Client clique "Payer" sur /client/orders/{order}
 *   2. Ce controller crée une Session Stripe Checkout (server-side)
 *   3. Client est redirigé vers la page Stripe hébergée
 *   4. Après paiement → Stripe redirige vers /payment/success
 *   5. Stripe envoie un webhook checkout.session.completed
 *   6. StripeWebhookController confirme et marque la commande PAID
 *
 * IMPORTANT: La page /payment/success n'est qu'une confirmation visuelle.
 * La source de vérité est TOUJOURS le webhook Stripe (jamais le redirect).
 *
 * @see App\Http\Controllers\Webhook\StripeWebhookController
 */
class OrderCheckoutController extends Controller
{
    public function checkout(Request $request, Order $order): RedirectResponse
    {
        // Protection IDOR : seul le propriétaire peut payer
        abort_if($order->user_id !== $request->user()->id, 403, 'Accès non autorisé.');


        // Seules les commandes DONE peuvent être payées
        if ($order->status !== 'DONE') {
            return back()->with('error', 'Cette commande n\'est pas disponible au paiement.');
        }

        // Vérifier que Stripe est configuré
        $stripeKey = config('cashier.secret') ?: env('STRIPE_SECRET');
        if (! $stripeKey) {
            return back()->with('error', 'Le paiement en ligne n\'est pas encore configuré. Contactez-nous.');
        }

        try {
            Stripe::setApiKey($stripeKey);

            $amountCents = $order->total_price_cents ?? $order->base_price_cents ?? 0;
            $label       = "Restauration photographique — {$order->reference}";
            $label      .= " ({$order->photo_count} photo" . ($order->photo_count > 1 ? 's' : '') . ')';

            $session = Session::create([
                'mode'         => 'payment',
                'currency'     => 'eur',
                'line_items'   => [[
                    'price_data' => [
                        'currency'     => 'eur',
                        'unit_amount'  => $amountCents,
                        'product_data' => [
                            'name'        => $label,
                            'description' => "Restauration {$order->damage_level} — OmnyRestore",
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'success_url'  => route('payment.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'   => route('client.orders.show', $order),
                'metadata'     => [
                    'order_id'   => $order->id,
                    'order_ref'  => $order->reference,
                    'user_id'    => $order->user_id,
                ],
                'customer_email' => $order->user->email,
                'locale'         => 'fr',
            ]);

            // Sauvegarder le session ID pour le retrouver dans le webhook
            $order->update(['payment_intent_id' => $session->payment_intent ?? $session->id]);

            return redirect($session->url);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return back()->with('error', 'Erreur Stripe : ' . $e->getMessage());
        }
    }
}

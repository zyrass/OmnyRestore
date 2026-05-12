<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateOrderZipJob;
use App\Mail\OrderPaidConfirmation;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        // ── Calcul du montant ─────────────────────────────────────────────
        // total_price_cents = HT net (après remise coupon), peut valoir 0
        // On compare !== null pour distinguer "non fixé" de "offert"
        $htCents  = $order->total_price_cents !== null
            ? $order->total_price_cents
            : ($order->base_price_cents ?? 0);

        $tvaC     = (int) round($htCents * 0.20);
        $ttcCents = $htCents + $tvaC;   // montant TTC à facturer au client

        // ── Cas coupon 100% : commande offerte, pas de Stripe ────────────
        if ($ttcCents === 0) {
            $order->update([
                'payment_status'    => 'paid',
                'status'            => 'PAID',
                'paid_at'           => now(),
                'payment_intent_id' => 'coupon_free_' . $order->reference,
            ]);

            // Générer le ZIP comme après un vrai paiement Stripe
            GenerateOrderZipJob::dispatch($order)->onQueue('default');

            // Notifier le client par email
            try {
                Mail::to($order->user->email)->queue(new OrderPaidConfirmation($order));
            } catch (\Throwable $e) {
                Log::error("Coupon free: mail échec {$order->reference}", ['error' => $e->getMessage()]);
            }

            return redirect()->route('client.orders.show', $order)
                ->with('success', 'Votre commande est offerte grâce à votre coupon. Votre archive ZIP est en cours de préparation !');
        }

        // Vérifier que Stripe est configuré
        $stripeKey = config('cashier.secret') ?: env('STRIPE_SECRET');
        if (! $stripeKey) {
            return back()->with('error', 'Le paiement en ligne n\'est pas encore configuré. Contactez-nous.');
        }

        try {
            Stripe::setApiKey($stripeKey);

            $photoCount = $order->photo_count;
            $label      = "Restauration photographique — {$order->reference}";
            $label     .= " ({$photoCount} photo" . ($photoCount > 1 ? 's' : '') . ')';

            // unit_amount = montant TTC en centimes (Stripe attend le montant final payé)
            $session = Session::create([
                'mode'         => 'payment',
                'currency'     => 'eur',
                'line_items'   => [[
                    'price_data' => [
                        'currency'     => 'eur',
                        'unit_amount'  => $ttcCents,   // ← TTC, pas HT
                        'product_data' => [
                            'name'        => $label,
                            'description' => "Restauration {$order->damage_level} — OmnyRestore (TVA 20% incluse)",
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'success_url'    => route('payment.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'     => route('client.orders.show', $order),
                'metadata'       => [
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

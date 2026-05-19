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

        // Vérification stricte de l'état du coupon appliqué
        if ($order->coupon_code) {
            $coupon = \App\Models\Coupon::where('code', $order->coupon_code)->first();
            $isCouponUsed = $coupon && $coupon->max_uses && $coupon->used_count >= $coupon->max_uses;
            $isCouponExpired = $coupon && $coupon->expires_at && $coupon->expires_at->isPast();
            $isCouponInactive = $coupon && !$coupon->is_active;

            if ($isCouponUsed || $isCouponExpired || $isCouponInactive) {
                return redirect()->route('client.orders.show', $order)
                    ->with('show_coupon_used_warning', true)
                    ->with('warning_coupon_code', $order->coupon_code);
            }
        }

        // ── Calcul du montant TTC ────────────────────────────────────────
        // ⚠️ Ne PAS faire round(htCents * 0.20) sur le total cumulé :
        //    3 photos light (83¢ HT) + 1 medium (167¢ HT) = 416¢ HT
        //    416 * 0.20 = 83.2 → round → 83¢ TVA → TTC = 499¢ = 4,99€ ← perd 1 centime
        //
        // Solution : recalculer le TTC exact à partir du HT par photo.
        //    htToTtc(83) = round(83 * 1.20) = round(99.6) = 100¢ par photo ✓
        //    Somme TTC = 3×100¢ + 1×200¢ = 500¢ = 5,00€ ✓
        $baseHtC     = $order->base_price_cents ?? 0;
        $discountC   = $order->discount_cents ?? 0;
        $finalHtC    = $order->total_price_cents !== null ? $order->total_price_cents : max(0, $baseHtC - $discountC);
        
        $ttcCents = $order->getAmountTtcCents();

        // ── Cas coupon 100% : commande offerte, pas de Stripe ────────────
        if ($ttcCents === 0) {
            $order->update([
                'payment_status'    => 'paid',
                'status'            => 'PAID',
                'paid_at'           => now(),
                'payment_intent_id' => 'coupon_free_' . $order->reference,
                'billing_name'      => $order->user->name,
                'billing_email'     => $order->user->email,
            ]);

            // Générer le ZIP comme après un vrai paiement Stripe
            GenerateOrderZipJob::dispatch($order)->onQueue('default');

            // Notifier le client par email : On attend la fin du job ZIP pour envoyer l'email de livraison finale.

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
                // Propage les métadonnées au PaymentIntent pour les webhooks payment_intent.*
                // Sans ça, payment_intent.payment_failed ne contient pas order_id.
                'payment_intent_data' => [
                    'metadata' => [
                        'order_id'  => $order->id,
                        'order_ref' => $order->reference,
                        'user_id'   => $order->user_id,
                    ],
                ],
                'customer_email' => $order->user->email,
                'locale'         => 'fr',
            ]);

            // Sauvegarder le session ID pour le retrouver dans le webhook et figer les infos de facturation
            $order->update([
                'payment_intent_id' => $session->payment_intent ?? $session->id,
                'billing_name'      => $order->user->name,
                'billing_email'     => $order->user->email,
            ]);

            return redirect($session->url);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return back()->with('error', 'Erreur Stripe : ' . $e->getMessage());
        }
    }
}

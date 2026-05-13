<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateOrderZipJob;
use App\Models\Order;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Stripe;

/**
 * PaymentSuccessController — Gère le retour Stripe après paiement
 *
 * Stripe redirige ici après un paiement réussi avec :
 *   ?session_id={CHECKOUT_SESSION_ID}
 *
 * Ce controller est un DOUBLE FILET de sécurité :
 *   1. Le webhook Stripe est la source de vérité principale (StripeWebhookController)
 *   2. Si le webhook échoue (local dev sans tunnel, délai réseau...), ce controller
 *      vérifie directement la session Stripe et marque l'ordre comme PAID.
 *
 * Idempotence : si l'ordre est déjà PAID (webhook déjà traité), on passe.
 *
 * Flow :
 *   Stripe → /payment/success?session_id=cs_xxx
 *     → Vérification session API Stripe
 *     → Si payment_status = paid → marquer Order PAID + dispatch ZIP
 *     → Redirect → /client/orders/{order} avec message flash
 *
 * @see App\Http\Controllers\Webhook\StripeWebhookController
 * @see App\Jobs\GenerateOrderZipJob
 */
class PaymentSuccessController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function handle(Request $request): RedirectResponse|\Illuminate\View\View
    {
        $sessionId = $request->query('session_id');

        // Pas de session_id → afficher la page générique (accès direct)
        if (! $sessionId) {
            return view('pages.payment.success');
        }

        $stripeKey = config('cashier.secret') ?: env('STRIPE_SECRET');
        if (! $stripeKey) {
            Log::warning('PaymentSuccessController: STRIPE_SECRET manquant');
            return view('pages.payment.success');
        }

        try {
            Stripe::setApiKey($stripeKey);
            $session = Session::retrieve($sessionId);
        } catch (\Throwable $e) {
            Log::error('PaymentSuccessController: impossible de récupérer la session Stripe', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
            return view('pages.payment.success');
        }

        // Récupérer l'order_id depuis les métadonnées Stripe
        $orderId = $session->metadata->order_id ?? null;
        if (! $orderId) {
            Log::error('PaymentSuccessController: order_id absent des métadonnées Stripe', [
                'session_id' => $sessionId,
            ]);
            return view('pages.payment.success');
        }

        $order = Order::find($orderId);
        if (! $order) {
            Log::error("PaymentSuccessController: commande #{$orderId} introuvable");
            return view('pages.payment.success');
        }

        // ── Vérification sécurité ownership ────────────────────────────────
        // L'utilisateur connecté doit être le propriétaire de la commande
        if ($request->user()?->id !== $order->user_id) {
            abort(403, 'Cette commande ne vous appartient pas.');
        }

        // ── Idempotence : ne pas re-traiter si déjà PAID ───────────────────
        if ($order->payment_status === 'paid') {
            Log::info("PaymentSuccessController: commande {$order->reference} déjà payée — redirect vers page confirmation");
            return redirect()->route('client.orders.payment-success', $order);
        }

        // ── Vérifier que Stripe confirme le paiement ───────────────────────
        if ($session->payment_status !== 'paid') {
            Log::warning("PaymentSuccessController: session {$sessionId} pas encore paid (status: {$session->payment_status})");
            return redirect()->route('client.orders.show', $order)
                ->with('error', 'Le paiement est en cours de traitement. Actualisez dans quelques secondes.');
        }

        // ── Marquer la commande PAID (fallback webhook) ────────────────────
        // ⚠️ status et payment_status sont EXCLUS de $fillable intentionnellement.
        // markAsPaid() est la seule méthode correcte. Elle appelle guardTransition()
        // qui lève une exception si le statut n'est plus DONE (ex: webhook déjà passé).
        try {
            $paymentIntentId = $session->payment_intent ?? ('cs_' . $session->id);
            $order->markAsPaid($paymentIntentId); // status=PAID, payment_status=paid, paid_at=now()

            $this->audit->orderStatusChanged($order, 'DONE', 'PAID');

            // ── Générer le ZIP (idempotent) ────────────────────────────────
            GenerateOrderZipJob::dispatch($order)->onQueue('default');

            Log::info("PaymentSuccessController: commande {$order->reference} marquée PAID via redirect Stripe", [
                'session_id' => $sessionId,
                'user_id'    => $order->user_id,
            ]);
        } catch (\InvalidArgumentException) {
            // Le webhook a déjà transitionné — pas un problème, on redirige quand même.
            Log::info("PaymentSuccessController: commande {$order->reference} déjà traitée par le webhook (statut: {$order->status})");
        }

        // L'email OrderPaidConfirmation est envoyé par l'OrderObserver (status→PAID)

        Log::info("PaymentSuccessController: commande {$order->reference} marquée PAID via redirect Stripe", [
            'session_id' => $sessionId,
            'user_id'    => $order->user_id,
        ]);

        return redirect()->route('client.orders.payment-success', $order);
    }
}

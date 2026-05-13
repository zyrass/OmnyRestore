<?php

use App\Http\Controllers\Webhook\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Webhook Routes — OmnyRestore
 *
 * Routes for receiving external webhook events from third-party services.
 *
 * CRITICAL — CSRF Exemption:
 *   Stripe (and all external webhooks) cannot provide a CSRF token.
 *   These routes MUST be excluded from CSRF verification.
 *   Add to bootstrap/app.php:
 *     $middleware->validateCsrfTokens(except: ['/webhook/stripe']);
 *
 * Security:
 *   The absence of CSRF protection is compensated by Stripe's HMAC-SHA256
 *   signature verification (handled by Laravel Cashier's base controller).
 *   An attacker cannot forge a valid signature without the STRIPE_WEBHOOK_SECRET.
 *
 * Monitoring:
 *   Stripe logs all webhook delivery attempts in the dashboard:
 *   https://dashboard.stripe.com/webhooks
 *   Failed deliveries are retried for up to 72 hours.
 */

// Stripe Webhook endpoint
// POST /webhook/stripe
// No auth middleware — Stripe authenticates via HMAC signature
// Throttle : 30 requêtes/min (Stripe relance jusqu'à 3×/h pendant 72h en cas d'échec).
// Un bot sans clé HMAC valide ne peut pas forger de requête — ce throttle est un
// filet de sécurité supplémentaire contre le flood réseau brut.
Route::post('/webhook/stripe', [StripeWebhookController::class, 'handleWebhook'])
     ->name('webhook.stripe')
     ->middleware('throttle:30,1');

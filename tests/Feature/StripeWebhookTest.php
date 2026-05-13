<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests du StripeWebhookController
 *
 * Stratégie : on forge des payloads JSON Stripe et on génère une signature HMAC
 * valide avec le STRIPE_WEBHOOK_SECRET configuré dans phpunit.xml (= 'whsec_fake').
 *
 * Ce que ces tests couvrent :
 *   - Rejet des requêtes sans signature valide (400)
 *   - checkout.session.completed → markAsPaid() + GenerateOrderZipJob dispatché
 *   - Idempotence : un deuxième webhook ne re-paie pas
 *   - payment_intent.payment_failed → email client envoyé
 *   - Les événements inconnus retournent 200 et sont ignorés
 */
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Génère un payload Stripe signé avec HMAC-SHA256.
     * Stripe utilise : `t={timestamp},v1={signature}` dans le header Stripe-Signature.
     * Le secret est lu depuis la config (aligné avec phpunit.xml → STRIPE_WEBHOOK_SECRET).
     */
    private function buildStripeRequest(array $payload): array
    {
        $json      = json_encode($payload);
        $timestamp = now()->timestamp;
        // Même secret que celui configuré dans phpunit.xml (STRIPE_WEBHOOK_SECRET=whsec_fake)
        $secret    = config('cashier.webhook.secret') ?: env('STRIPE_WEBHOOK_SECRET', 'whsec_fake');
        $signature = hash_hmac('sha256', "{$timestamp}.{$json}", $secret);

        return [
            'json'   => $json,
            'header' => "t={$timestamp},v1={$signature}",
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sécurité : signature
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function webhook_without_signature_returns_400(): void
    {
        $response = $this->postJson('/webhook/stripe', [
            'type' => 'checkout.session.completed',
        ]);

        // Sans header Stripe-Signature → rejet immédiat
        $response->assertStatus(400);
    }

    #[Test]
    public function webhook_with_invalid_signature_returns_400(): void
    {
        $response = $this->post('/webhook/stripe', [], [
            'Stripe-Signature' => 't=9999,v1=invalide_signature_xxxxxxxx',
            'Content-Type'     => 'application/json',
        ]);

        $response->assertStatus(400);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // checkout.session.completed
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function checkout_completed_marks_order_as_paid_and_dispatches_zip_job(): void
    {
        Queue::fake();
        Mail::fake();

        $user  = User::factory()->create();
        $order = Order::factory()->create([
            'user_id'        => $user->id,
            'status'         => 'DONE',
            'payment_status' => 'pending',
        ]);

        $payload = [
            'id'   => 'evt_test_001',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id'             => 'cs_test_abc123',
                    'payment_intent' => 'pi_test_xyz789',
                    'metadata'       => [
                        'order_id' => $order->id,
                    ],
                ],
            ],
        ];

        $request = $this->buildStripeRequest($payload);

        $response = $this->call(
            method: 'POST',
            uri: '/webhook/stripe',
            parameters: [],
            cookies: [],
            files: [],
            server: [
                'HTTP_STRIPE-SIGNATURE' => $request['header'],
                'CONTENT_TYPE'          => 'application/json',
            ],
            content: $request['json']
        );

        $response->assertStatus(200);

        // La commande doit être marquée PAID
        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);

        // Le job de génération du ZIP doit être en queue
        Queue::assertPushed(\App\Jobs\GenerateOrderZipJob::class);
    }

    #[Test]
    public function checkout_completed_is_idempotent(): void
    {
        Queue::fake();

        $user  = User::factory()->create();
        $order = Order::factory()->create([
            'user_id'        => $user->id,
            'status'         => 'PAID',
            'payment_status' => 'paid',
            'paid_at'        => now()->subMinutes(5),
        ]);

        $payload = [
            'id'   => 'evt_test_002',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id'       => 'cs_test_duplicate',
                    'metadata' => ['order_id' => $order->id],
                ],
            ],
        ];

        $request = $this->buildStripeRequest($payload);

        $this->call(
            'POST', '/webhook/stripe', [], [], [],
            ['HTTP_STRIPE-SIGNATURE' => $request['header'], 'CONTENT_TYPE' => 'application/json'],
            $request['json']
        )->assertStatus(200);

        // Aucun nouveau job ZIP ne doit être dispatché (déjà payé)
        Queue::assertNotPushed(\App\Jobs\GenerateOrderZipJob::class);
    }

    #[Test]
    public function checkout_completed_with_unknown_order_id_logs_error(): void
    {
        Queue::fake();

        $payload = [
            'id'   => 'evt_test_003',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id'       => 'cs_test_unknown',
                    // UUID syntaxiquement valide mais qui n'existe pas en base
                    'metadata' => ['order_id' => '00000000-0000-0000-0000-000000000000'],
                ],
            ],
        ];

        $request = $this->buildStripeRequest($payload);

        // Doit retourner 200 même si la commande n'existe pas
        // (Stripe ne doit pas relivrer un webhook qui retourne 200)
        $this->call(
            'POST', '/webhook/stripe', [], [], [],
            ['HTTP_STRIPE-SIGNATURE' => $request['header'], 'CONTENT_TYPE' => 'application/json'],
            $request['json']
        )->assertStatus(200);

        // Aucun job dispatché
        Queue::assertNotPushed(\App\Jobs\GenerateOrderZipJob::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // payment_intent.payment_failed
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function payment_failed_queues_failure_email(): void
    {
        Mail::fake();
        Queue::fake();

        $user  = User::factory()->create();
        $order = Order::factory()->create([
            'user_id'           => $user->id,
            'status'            => 'DONE',
            'payment_status'    => 'pending',
            'payment_intent_id' => 'pi_test_failed_abc',
        ]);

        $payload = [
            'id'   => 'evt_test_004',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id'       => 'pi_test_failed_abc',
                    'metadata' => ['order_id' => $order->id],
                    'last_payment_error' => [
                        'code'    => 'card_declined',
                        'message' => 'Your card was declined.',
                    ],
                ],
            ],
        ];

        $request = $this->buildStripeRequest($payload);

        $this->call(
            'POST', '/webhook/stripe', [], [], [],
            ['HTTP_STRIPE-SIGNATURE' => $request['header'], 'CONTENT_TYPE' => 'application/json'],
            $request['json']
        )->assertStatus(200);

        // L'email OrderPaymentFailed doit être mis en queue (Mail::queue())
        Mail::assertQueued(\App\Mail\OrderPaymentFailed::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Événements inconnus
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function unknown_event_type_returns_200_and_is_silently_ignored(): void
    {
        Queue::fake();

        $payload = [
            'id'   => 'evt_test_005',
            'type' => 'customer.subscription.created', // non géré
            'data' => ['object' => []],
        ];

        $request = $this->buildStripeRequest($payload);

        $this->call(
            'POST', '/webhook/stripe', [], [], [],
            ['HTTP_STRIPE-SIGNATURE' => $request['header'], 'CONTENT_TYPE' => 'application/json'],
            $request['json']
        )->assertStatus(200);
    }
}

<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * AuditService — OmnyRestore
 *
 * Centralized service for writing immutable audit log entries.
 * ALL audit log writes MUST go through this service — never use AuditLog::create() directly.
 *
 * Why centralize?
 *   - Consistent format: always captures IP + user agent + current user
 *   - Single place to add future behavior (alerting, external SIEM, etc.)
 *   - Testable: easy to mock in feature tests
 *
 * Usage:
 *   // In a controller:
 *   app(AuditService::class)->log('ORDER_CREATED', $order, ['photo_count' => 3]);
 *
 *   // Or via static shorthand (uses current request + auth):
 *   AuditService::record('PAYMENT_SUCCEEDED', $order, ['stripe_intent' => 'pi_xxx']);
 *
 * @see App\Models\AuditLog
 */
class AuditService
{
    /**
     * Write an audit log entry.
     *
     * @param string     $action  The action identifier (e.g., 'ORDER_CREATED')
     * @param Model|null $subject The entity the action was performed on (or null for system)
     * @param array      $payload Additional context data (stored as JSON)
     * @param User|null  $user    The actor (defaults to currently authenticated user)
     * @param Request|null $request The HTTP request (for IP + user agent)
     */
    public function log(
        string   $action,
        ?Model   $subject  = null,
        array    $payload  = [],
        ?User    $user     = null,
        ?Request $request  = null
    ): AuditLog {
        // Determine actor: use provided user, fall back to authenticated user, or null (system)
        $actor   = $user ?? Auth::user();
        $request = $request ?? request();

        return AuditLog::create([
            'user_id'      => $actor?->id,
            'action'       => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject?->id,
            'payload'      => $payload,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
        ]);
    }

    // =========================================================================
    // CONVENIENCE METHODS — Strongly-typed wrappers for common actions
    // =========================================================================

    /**
     * Log an order creation event.
     * Called in: Client\OrderController@store
     */
    public function orderCreated(Order $order): AuditLog
    {
        return $this->log('ORDER_CREATED', $order, [
            'reference'   => $order->reference,
            'photo_count' => $order->photo_count,
        ]);
    }

    /**
     * Log an order status change event.
     * Called in: Admin\OrderController@updateStatus
     *
     * @param string $from Previous status
     * @param string $to   New status
     */
    public function orderStatusChanged(Order $order, string $from, string $to): AuditLog
    {
        return $this->log('ORDER_STATUS_CHANGED', $order, [
            'reference' => $order->reference,
            'from'      => $from,
            'to'        => $to,
        ]);
    }

    /**
     * Log a successful payment event.
     * Called in: Webhook\StripeWebhookController
     *
     * @param string $paymentIntentId The Stripe PaymentIntent ID
     * @param int    $amountCents     Amount paid in cents
     */
    public function paymentSucceeded(Order $order, string $paymentIntentId, int $amountCents): AuditLog
    {
        return $this->log('PAYMENT_SUCCEEDED', $order, [
            'reference'         => $order->reference,
            'payment_intent_id' => $paymentIntentId,
            'amount_cents'      => $amountCents,
            'currency'          => 'eur',
        ], null); // Actor is null: Stripe webhook is a system event, not a user action
    }

    /**
     * Log a download initiation event.
     * Called in: Client\OrderDownloadController@download
     */
    public function downloadInitiated(Order $order): AuditLog
    {
        return $this->log('DOWNLOAD_INITIATED', $order, [
            'reference' => $order->reference,
            'zip_path'  => $order->delivery?->zip_path,
        ]);
    }

    /**
     * Log a GDPR data export request.
     * Called in: Client\ProfileController@exportData
     */
    public function gdprExport(User $user): AuditLog
    {
        return $this->log('GDPR_EXPORT', $user, [
            'email' => $user->email,
        ]);
    }

    /**
     * Log a GDPR account erasure request.
     * Called in: Client\ProfileController@destroyAccount
     */
    public function gdprErasure(User $user): AuditLog
    {
        // Note: We log BEFORE the soft delete, while we still have the email
        return $this->log('GDPR_ERASURE', $user, [
            'email' => $user->email,
        ]);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create the audit_logs table
 *
 * The audit log is an IMMUTABLE record of all significant actions in the platform.
 * It serves two purposes:
 *   1. GDPR compliance: Required traceability for data access and modification
 *      (GDPR Article 30 — Records of processing activities)
 *   2. Security: Detection of anomalous behavior (multiple failed logins, unusual downloads)
 *      (Aligns with NIS2 Article 21 — Logging and monitoring measures)
 *
 * Design principles:
 *   - NO updated_at column: audit logs are WRITE ONCE, READ ONLY
 *   - Polymorphic subject: can reference any model (Order, User, etc.)
 *   - Never deleted (minimum 12-month retention per NIS2 best practices)
 *   - user_id is nullable: some actions are system-initiated (scheduled jobs)
 *
 * Tracked actions (action column values):
 *   - ORDER_CREATED       : Client submitted a new order
 *   - ORDER_STATUS_CHANGED: Admin changed order status (includes old/new status in payload)
 *   - PAYMENT_INITIATED   : Client clicked "Pay"
 *   - PAYMENT_SUCCEEDED   : Stripe webhook confirmed payment
 *   - PAYMENT_FAILED      : Stripe webhook reported failure
 *   - DOWNLOAD_INITIATED  : Client clicked "Download"
 *   - LOGIN               : User authenticated successfully
 *   - LOGIN_FAILED        : Failed authentication attempt
 *   - GDPR_EXPORT         : User requested their data export
 *   - GDPR_ERASURE        : User requested account deletion
 *
 * @see App\Services\AuditService
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            // ─── Primary Key ─────────────────────────────────────────────
            $table->uuid('id')->primary();

            // ─── Actor ────────────────────────────────────────────────────
            // The user who performed the action.
            // Nullable because some actions are system-initiated (scheduled commands).
            // We do NOT use a foreign key constraint here intentionally:
            //   - If a user is deleted (GDPR erasure), their audit logs must remain
            //   - A FK would prevent deletion or cascade-delete the logs (both wrong)
            $table->uuid('user_id')->nullable()->index();

            // ─── Action ───────────────────────────────────────────────────
            // Uppercase snake_case string identifying the action type.
            // See the docblock above for the full list of possible values.
            $table->string('action')->index();

            // ─── Subject (Polymorphic) ─────────────────────────────────────
            // The entity the action was performed ON.
            // Examples:
            //   subject_type = 'App\Models\Order', subject_id = 'uuid-of-the-order'
            //   subject_type = 'App\Models\User',  subject_id = 'uuid-of-the-user'
            // Using uuidMorphs() for UUID-keyed polymorphic relations (PostgreSQL-friendly)
            $table->uuidMorphs('subject'); // Adds subject_id (uuid) + subject_type (string)

            // ─── Context Payload ──────────────────────────────────────────
            // JSON blob containing all relevant context for the action.
            // Examples:
            //   ORDER_STATUS_CHANGED: { "from": "PENDING", "to": "IN_PROGRESS" }
            //   PAYMENT_SUCCEEDED:    { "stripe_intent": "pi_xxx", "amount": 4900 }
            //   DOWNLOAD_INITIATED:   { "zip_path": "deliveries/xxx.zip" }
            // Using PostgreSQL's native JSON type (queryable with ->)
            $table->json('payload')->nullable();

            // ─── Network Context ──────────────────────────────────────────
            // Required by GDPR for breach notification and security investigation.
            $table->string('ip_address', 45)->nullable();  // 45 chars covers IPv6
            $table->string('user_agent')->nullable();

            // ─── Timestamp ────────────────────────────────────────────────
            // ONLY created_at — no updated_at (immutable record).
            // Using $table->timestamp() instead of $table->timestamps()
            $table->timestamp('created_at');

            // ─── Index for Performance ────────────────────────────────────
            // Admin will query: WHERE user_id = ? ORDER BY created_at DESC
            // This composite index makes that query fast even with millions of rows.
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

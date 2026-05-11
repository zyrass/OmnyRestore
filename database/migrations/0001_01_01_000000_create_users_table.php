<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Modify the users table for OmnyRestore
 *
 * The default Laravel users table only includes basic auth fields.
 * This migration DROPS and RECREATES the users table to add:
 *   - UUID primary key (instead of auto-increment integer)
 *   - Role field (client | admin) for RBAC
 *   - Stripe customer ID for payment history
 *   - GDPR compliance fields (rgpd_consent_at, marketing_consent)
 *   - Soft deletes for GDPR Right to Erasure (keeps DB integrity, anonymizes data)
 *
 * PostgreSQL-specific notes:
 *   - uuid() uses gen_random_uuid() natively in PG 13+ (no extension needed)
 *   - timestamps() creates created_at and updated_at as timestamptz
 *   - softDeletes() adds deleted_at (nullable timestamp) for Eloquent soft delete
 *
 * @see App\Models\User
 * @see https://laravel.com/docs/migrations
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the full users table structure for OmnyRestore.
     * Called by: php artisan migrate
     */
    public function up(): void
    {
        // Drop the default Laravel users table before recreating
        // (Breeze already created a basic version)
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            // ─── Primary Key ─────────────────────────────────────────────
            // UUID instead of auto-increment integer.
            // Advantages: globally unique, non-sequential (prevents IDOR enumeration),
            // safe to expose in URLs (/client/orders/{uuid}).
            $table->uuid('id')->primary();

            // ─── Identity ────────────────────────────────────────────────
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken(); // Used by "remember me" auth functionality

            // ─── Role-Based Access Control (RBAC) ────────────────────────
            // Simple enum-like string: 'client' or 'admin'.
            // We use a string instead of a PHP enum for database flexibility.
            // Authorization is enforced via Middleware (EnsureIsAdmin) and Policies.
            $table->string('role')->default('client');

            // ─── Stripe Integration ───────────────────────────────────────
            // Laravel Cashier stores the Stripe Customer ID here.
            // Created when the user first attempts to pay.
            // Allows retrieving full payment history from Stripe dashboard.
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();        // e.g., 'card'
            $table->string('pm_last_four')->nullable();   // e.g., '4242'
            $table->timestamp('trial_ends_at')->nullable();

            // ─── GDPR Compliance Fields ───────────────────────────────────
            // rgpd_consent_at: Timestamp of when the user accepted the privacy policy.
            // This is the legally required proof of informed consent (GDPR Art. 7).
            // Must NOT be null — consent is mandatory at registration.
            $table->timestamp('rgpd_consent_at')->nullable();

            // marketing_consent: Whether the user opted in to marketing emails.
            // Separate from service emails (order status) which are always sent.
            // Default false — opt-in model required by GDPR.
            $table->boolean('marketing_consent')->default(false);

            // ─── Timestamps ───────────────────────────────────────────────
            $table->timestamps(); // created_at, updated_at

            // ─── Soft Delete (GDPR Right to Erasure) ─────────────────────
            // Instead of permanently deleting user records (which would break
            // foreign key constraints with orders/audit_logs), we soft-delete.
            // When deleted_at is set, Eloquent hides the record from all queries.
            // A separate scheduled job then anonymizes personal fields.
            $table->softDeletes(); // deleted_at (nullable timestamp)
        });

        // ─── Password Reset Tokens ────────────────────────────────────────────
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops all user-related tables.
     * Called by: php artisan migrate:rollback
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};

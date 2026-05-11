<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create the orders table
 *
 * The orders table is the core of OmnyRestore's business logic.
 * It tracks the full lifecycle of a photo restoration order:
 *   PENDING → IN_PROGRESS → DONE → (payment) → downloaded
 *
 * Key design decisions:
 *   - UUID primary key (non-sequential, safe for public URLs)
 *   - Status as string enum (PENDING | IN_PROGRESS | DONE | CANCELLED)
 *   - Amount stored in cents (integer) to avoid floating-point precision issues
 *     BUT here we use decimal(10,2) for human readability (standard for accounting)
 *   - Payment fields mirror Stripe's PaymentIntent structure
 *   - No hard foreign key cascade delete — orders must be preserved for accounting
 *     (5-year legal retention in France — CGI Art. 302 septies A)
 *
 * @see App\Models\Order
 * @see App\Models\User
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            // ─── Primary Key ─────────────────────────────────────────────
            $table->uuid('id')->primary();

            // ─── Ownership ───────────────────────────────────────────────
            // The client who placed this order.
            // constrained() automatically adds FK to users.id.
            // onDelete('restrict') prevents deleting a user who has orders.
            // For GDPR erasure: the user is soft-deleted and their name/email
            // are anonymized, but the order record is preserved for accounting.
            $table->foreignUuid('user_id')
                  ->constrained('users')
                  ->onDelete('restrict'); // Protect financial records

            // ─── Reference ───────────────────────────────────────────────
            // Human-readable order number displayed to client and admin.
            // Format: ORD-2026-0001 (year + sequential counter)
            // Generated in the Order model's boot() method.
            $table->string('reference')->unique();

            // ─── Order Details ────────────────────────────────────────────
            // Client's description of what restoration work is needed.
            // E.g., "Photo de mariage de 1965 — endommagée par l'eau"
            $table->text('description');

            // Number of photos uploaded in this order (1 to N).
            // Used for pricing calculation.
            $table->unsignedSmallInteger('photo_count')->default(1);

            // ─── Status State Machine ─────────────────────────────────────
            // Possible transitions:
            //   PENDING      → IN_PROGRESS (admin takes charge)
            //   PENDING      → CANCELLED   (admin or client cancels)
            //   IN_PROGRESS  → DONE        (admin uploads restored photos)
            //   IN_PROGRESS  → CANCELLED   (unrestorable photos)
            //
            // Status is validated in the Order model before saving.
            // State transitions are enforced in Livewire components.
            $table->string('status')->default('PENDING');
            // Index for fast filtering in admin dashboard (WHERE status = 'PENDING')
            $table->index('status');

            // ─── Pricing ─────────────────────────────────────────────────
            // Prices are set by admin when reviewing the order.
            // All amounts in EUROS — use decimal(10,2) for exact arithmetic.
            // Note: Stripe works in cents — convert before creating PaymentIntent.
            $table->decimal('amount_ht', 10, 2)->nullable();   // Pre-tax amount
            $table->decimal('tva_rate', 5, 2)->default(20.00); // VAT rate (20% in France)
            $table->decimal('amount_ttc', 10, 2)->nullable();  // Total including VAT

            // ─── Stripe Payment Fields ─────────────────────────────────────
            // payment_intent_id: Stripe's PaymentIntent ID (pi_xxxxxxx)
            // Set when the client initiates checkout via Stripe.
            $table->string('payment_intent_id')->nullable()->index();

            // payment_status: Mirrors Stripe's payment state.
            // Values: 'pending' | 'paid' | 'refunded' | 'failed'
            $table->string('payment_status')->default('pending');

            // paid_at: Set by the Stripe webhook handler when payment succeeds.
            // This is the definitive proof of payment for accounting.
            $table->timestamp('paid_at')->nullable();

            // ─── Delivery Timestamp ───────────────────────────────────────
            // Set when admin marks the order as DONE and uploads restored photos.
            // Used to calculate the 6-month photo retention deadline (GDPR).
            $table->timestamp('delivered_at')->nullable();

            // ─── Timestamps ───────────────────────────────────────────────
            $table->timestamps(); // created_at, updated_at

            // ─── Notes ───────────────────────────────────────────────────
            // Optional admin notes about the restoration (not visible to client)
            $table->text('admin_notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

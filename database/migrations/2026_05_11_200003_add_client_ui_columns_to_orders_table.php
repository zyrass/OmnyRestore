<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add UI-specific columns to orders table
 *
 * The initial orders migration used generic `description`, `amount_ht`, `amount_ttc`.
 * The Livewire client UI requires more specific fields:
 *
 *   - damage_level  : determines the pricing tier (light = 1€/photo, heavy = 10€/photo)
 *   - instructions  : client's contextual notes (replaces generic `description`)
 *   - base_price_cents : estimated price in cents (avoids decimal precision issues)
 *   - total_price_cents: final price after admin review (what Stripe actually charges)
 *
 * The old amount_ht / amount_ttc columns are kept for backwards compat with accounting exports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Damage level chosen by the client at order creation
            // 'light' → 1€/photo | 'heavy' → 10€/photo
            $table->string('damage_level')->default('light')->after('photo_count');

            // Optional client instructions (replaces the mandatory `description`)
            // The description column is now nullable for backward compatibility
            $table->text('instructions')->nullable()->after('damage_level');
            $table->text('description')->nullable()->change(); // make existing column nullable

            // Estimated base price (set at creation, in cents)
            $table->unsignedInteger('base_price_cents')->nullable()->after('instructions');

            // Final price confirmed by admin (what Stripe charges)
            $table->unsignedInteger('total_price_cents')->nullable()->after('base_price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['damage_level', 'instructions', 'base_price_cents', 'total_price_cents']);
        });
    }
};

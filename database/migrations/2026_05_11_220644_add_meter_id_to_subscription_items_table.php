<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Migration Cashier doublon — protégée par guard hasColumn. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('subscription_items', 'stripe_meter_id')) {
            return;
        }

        Schema::table('subscription_items', function (Blueprint $table) {
            $table->string('stripe_meter_id')->nullable()->after('stripe_price');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_items', function (Blueprint $table) {
            $table->dropColumn('stripe_meter_id');
        });
    }
};

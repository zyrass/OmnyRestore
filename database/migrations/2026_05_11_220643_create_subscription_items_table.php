<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Migration Cashier doublon — protégée par guard hasTable. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscription_items')) {
            return;
        }

        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->string('stripe_id')->unique();
            $table->string('stripe_product');
            $table->string('stripe_price');
            $table->integer('quantity')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'stripe_price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
    }
};

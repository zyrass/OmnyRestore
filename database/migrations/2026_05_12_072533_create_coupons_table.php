<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            // Code unique (ex: "BIENVENUE10", "ETE2026")
            $table->string('code')->unique();

            // Description pour l'admin
            $table->string('description')->nullable();

            // Type de réduction
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            // value : % si percentage (ex: 10 = 10%), centimes si fixed (ex: 50 = 0,50 €)
            $table->unsignedInteger('value');

            // Montant minimum de commande HT pour appliquer le coupon (centimes)
            $table->unsignedInteger('min_order_cents')->default(0);

            // Limite d'utilisations (null = illimité)
            $table->unsignedInteger('max_uses')->nullable();

            // Compteur d'utilisations effectives
            $table->unsignedInteger('used_count')->default(0);

            // Date d'expiration (null = pas d'expiration)
            $table->timestamp('expires_at')->nullable();

            // Actif ou désactivé manuellement
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};

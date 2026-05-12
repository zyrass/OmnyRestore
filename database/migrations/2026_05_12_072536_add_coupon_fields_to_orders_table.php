<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Code du coupon appliqué (null si aucun)
            $table->string('coupon_code')->nullable()->after('admin_notes');

            // Montant de la réduction en centimes HT
            $table->unsignedInteger('discount_cents')->default(0)->after('coupon_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['coupon_code', 'discount_cents']);
        });
    }
};

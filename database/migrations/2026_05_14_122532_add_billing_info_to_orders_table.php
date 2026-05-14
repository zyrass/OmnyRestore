<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('billing_name')->nullable()->after('delivered_at');
            $table->string('billing_email')->nullable()->after('billing_name');
        });

        // Mise à jour rétroactive des commandes existantes avec les infos actuelles des clients.
        // Cela permet aux anciennes factures de rester intactes.
        DB::statement('
            UPDATE orders
            SET billing_name = users.name, billing_email = users.email
            FROM users
            WHERE orders.user_id = users.id
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['billing_name', 'billing_email']);
        });
    }
};

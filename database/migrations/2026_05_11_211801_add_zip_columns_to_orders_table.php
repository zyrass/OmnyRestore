<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les colonnes nécessaires au suivi des ZIPs de livraison.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Chemin relatif du ZIP dans Storage::disk('local')
            // Ex: orders/zips/omnyrestore_ORD-2026-0001_20260511_211800.zip
            $table->string('zip_path')->nullable()->after('delivered_at');

            // Date d'expiration du ZIP (90 jours après génération)
            $table->timestamp('zip_expires_at')->nullable()->after('zip_path');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['zip_path', 'zip_expires_at']);
        });
    }
};

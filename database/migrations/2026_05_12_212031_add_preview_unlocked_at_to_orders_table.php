<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute preview_unlocked_at à la table orders.
 *
 * Cette colonne est positionnée à null par défaut.
 * Elle est renseignée par UnlockPreviewController quand le client
 * clique sur le lien signé contenu dans l'email OrderReadyForPayment.
 *
 * Logique de la porte :
 *   status = DONE && preview_unlocked_at = null  → afficher "consultez votre email"
 *   status = DONE && preview_unlocked_at ≠ null  → afficher les aperçus filigranés
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('preview_unlocked_at')
                  ->nullable()
                  ->after('paid_at')
                  ->comment('Renseigné quand le client clique le lien signé de l\'email — débloque l\'aperçu DONE.');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('preview_unlocked_at');
        });
    }
};

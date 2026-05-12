<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute order_id, user_id et rejected_at à la table testimonials.
 *
 * order_id (uuid nullable) — lie le témoignage à la commande livrée.
 *   Contrainte UNIQUE : un seul avis par commande.
 *   Nullable pour permettre la création manuelle par l'admin.
 *
 * user_id (uuid nullable) — auteur du témoignage (client authentifié).
 *   Nullable pour permettre les imports manuels.
 *
 * rejected_at (timestamp nullable) — l'admin a rejeté le témoignage.
 *   Permet de distinguer "en attente" / "publié" / "rejeté" sans suppression.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->uuid('order_id')->nullable()->after('id')
                  ->comment('Commande livrée ayant motivé l\'avis — unique par commande.');
            $table->uuid('user_id')->nullable()->after('order_id')
                  ->comment('Client auteur — null pour les avis créés manuellement par l\'admin.');
            $table->timestamp('rejected_at')->nullable()->after('is_published')
                  ->comment('Null = en attente ou publié. Non-null = rejeté par l\'admin.');

            // Un seul avis par commande (null est toujours autorisé plusieurs fois)
            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
            $table->dropColumn(['order_id', 'user_id', 'rejected_at']);
        });
    }
};

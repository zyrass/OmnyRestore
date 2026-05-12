<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute anonymized_at à la table users.
 *
 * Ce timestamp est positionné lors de l'exécution de DeleteUserAction.
 * Il confirme que l'anonymisation RGPD a bien eu lieu (audit trail).
 *
 * Flux : utilisateur demande suppression
 *   → DeleteUserAction::execute()
 *     → nom/email anonymisés, médias supprimés, stripe_id effacé
 *     → anonymized_at = now()
 *     → soft-delete (deleted_at = now())
 *   → Les commandes sont conservées (obligation légale 10 ans) mais
 *     pointer vers un user anonymisé (deleted_at set, PII effacé).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('anonymized_at')
                  ->nullable()
                  ->after('deleted_at')
                  ->comment('Horodatage RGPD de l\'anonymisation — positionné par DeleteUserAction.');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('anonymized_at');
        });
    }
};

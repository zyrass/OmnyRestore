<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration Cashier (doublon) — ajout colonnes Stripe sur la table users.
 *
 * Note : Cette migration est un doublon de 2026_05_11_193806_create_customer_columns
 * générée automatiquement par `php artisan vendor:publish --tag=cashier-migrations`.
 * Elle est conservée mais protégée par un guard `hasColumn` pour éviter les erreurs
 * lors d'un `migrate:fresh` (les deux migrations seraient sinon en conflit).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guard : si stripe_id existe déjà (ajouté par la migration précédente), on saute
        if (Schema::hasColumn('users', 'stripe_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'stripe_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['stripe_id']);
            $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at']);
        });
    }
};

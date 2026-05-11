<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cashier customer columns migration — OmnyRestore
 *
 * NOTE: stripe_id, pm_type, pm_last_four, trial_ends_at sont déjà définis
 * dans 0001_01_01_000000_create_users_table.php pour centraliser la structure.
 *
 * Cette migration est conservée pour la compatibilité avec Cashier mais
 * elle est rendue no-op (skipée si les colonnes existent déjà).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Ajout conditionnel : ne pas recréer les colonnes si elles existent déjà.
            // Les colonnes sont définies dans create_users_table pour centraliser le schéma.
            if (! Schema::hasColumn('users', 'stripe_id')) {
                $table->string('stripe_id')->nullable()->index();
            }
            if (! Schema::hasColumn('users', 'pm_type')) {
                $table->string('pm_type')->nullable();
            }
            if (! Schema::hasColumn('users', 'pm_last_four')) {
                $table->string('pm_last_four', 4)->nullable();
            }
            if (! Schema::hasColumn('users', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Ne rien supprimer ici — géré par create_users_table rollback
    }
};

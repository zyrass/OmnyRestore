<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUuid('operator_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->after('user_id');
        });

        // Migration of existing admin to super-admin
        \Illuminate\Support\Facades\DB::table('users')
            ->where('role', 'admin')
            ->update(['role' => 'super-admin']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert role migration
        \Illuminate\Support\Facades\DB::table('users')
            ->where('role', 'super-admin')
            ->update(['role' => 'admin']);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['operator_id']);
            $table->dropColumn('operator_id');
        });
    }
};

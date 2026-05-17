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
        Schema::table('users', function (Blueprint $table) {
            $table->date('hire_date')->nullable()->after('suspended_at');
            $table->string('contract_type')->nullable()->after('hire_date'); // CDI, CDD, Freelance
            $table->decimal('net_salary', 10, 2)->nullable()->after('contract_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['hire_date', 'contract_type', 'net_salary']);
        });
    }
};

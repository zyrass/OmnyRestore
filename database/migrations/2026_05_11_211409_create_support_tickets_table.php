<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support Tickets — Système de tickets client/admin
 *
 * Un ticket est ouvert par un client, peut être lié à une commande.
 * L'admin répond depuis le back-office. Le client voit les réponses
 * dans son espace et reçoit un email à chaque réponse.
 *
 * Statuts :
 *   open     → Ouvert, en attente de traitement admin
 *   pending  → Admin a répondu, en attente de feedback client
 *   closed   → Résolu ou fermé
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->string('reference', 16)->unique(); // TKT-2026-0001
            $table->string('subject', 200);
            $table->enum('status', ['open', 'pending', 'closed'])->default('open');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();
        });

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->text('body');
            $table->boolean('is_admin')->default(false); // true = réponse de l'équipe OmnyRestore
            $table->boolean('is_read')->default(false);  // lu par le destinataire

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};

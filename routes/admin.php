<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/**
 * Admin Routes — OmnyRestore
 *
 * Back-office accessible uniquement aux utilisateurs avec role='admin'.
 * Stack middleware: auth → verified → admin (EnsureIsAdmin)
 */

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {

    // ─── Dashboard ────────────────────────────────────────────────────────
    // GET /admin/dashboard — KPIs + file d'attente des commandes PENDING
    Volt::route('/dashboard', 'pages.admin.dashboard')
        ->name('dashboard');

    // ─── Order Management ─────────────────────────────────────────────────
    // GET /admin/orders — Liste toutes les commandes (filtrables)
    Volt::route('/orders', 'pages.admin.orders.index')
        ->name('orders.index');

    // GET /admin/orders/{order} — Détail + actions admin (prise en charge, upload, prix)
    Volt::route('/orders/{order}', 'pages.admin.orders.show')
        ->name('orders.show');

    // PATCH /admin/orders/{order}/status — Transition de statut (via Livewire actions)
    Route::patch('/orders/{order}/status',
        \App\Http\Controllers\Admin\OrderController::class . '@updateStatus'
    )->name('orders.status');

    // POST /admin/orders/{order}/auto-restore — Restauration IA automatique (Phase 8)
    Route::post('/orders/{order}/auto-restore',
        \App\Http\Controllers\Admin\OrderAutoRestoreController::class . '@dispatch'
    )->name('orders.auto-restore');

    // ─── Support Tickets ──────────────────────────────────────────────────
    // GET /admin/tickets — Liste tous les tickets (filtrables par statut)
    Volt::route('/tickets', 'pages.admin.tickets.index')
        ->name('tickets.index');

    // GET /admin/tickets/{ticket} — Fil de conversation + réponse admin
    Volt::route('/tickets/{ticket}', 'pages.admin.tickets.show')
        ->name('tickets.show');

    // ─── Codes de réduction (Coupons) ───────────────────────────────────
    // GET /admin/coupons — Liste + création des codes de réduction
    Volt::route('/coupons', 'pages.admin.coupons.index')
        ->name('coupons.index');

});

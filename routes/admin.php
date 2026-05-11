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

});

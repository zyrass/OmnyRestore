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

    // ─── Clients ─────────────────────────────────────────────────────────
    // GET /admin/clients — Liste complète des clients avec CA payé
    Volt::route('/clients', 'pages.admin.clients.index')
        ->name('clients');

    // ─── Chiffre d'Affaire ───────────────────────────────────────────────
    // GET /admin/revenue — CA mensuel avec graphes Chart.js
    Volt::route('/revenue', 'pages.admin.revenue.index')
        ->name('revenue');

    Route::get('/revenue/export',
        \App\Http\Controllers\Admin\AdminRevenueExportController::class . '@download'
    )->name('revenue.export');

    // ─── Témoignages (modération) ─────────────────────────────────────────
    // GET /admin/testimonials — Modération (publier / rejeter / supprimer)
    Volt::route('/testimonials', 'pages.admin.testimonials.index')
        ->name('testimonials.index');

    // ─── Photos sécurisées ────────────────────────────────────────────────
    // GET /admin/orders/{order}/photos/{media}
    // Sert les photos retouched/originals depuis le disk privé (non public).
    Route::get('/orders/{order}/photos/{media}',
        [\App\Http\Controllers\Admin\AdminSecurePhotoController::class, 'show']
    )->name('orders.photo.show');

    // ─── Cellule de Crise (PRI) ──────────────────────────────────────────
    // Poste de commandement en cas d'incident majeur ou RGPD
    Volt::route('/incident-response', 'pages.admin.incident.index')
        ->name('incident.response');

    Route::get('/incident-response/export',
        \App\Http\Controllers\Admin\IncidentReportController::class . '@download'
    )->name('incident.export');

});

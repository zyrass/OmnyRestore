<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/**
 * Admin Routes — OmnyRestore
 *
 * Back-office réparti entre le 'staff' (opérateurs, marketing) et 'admin' (super-admin).
 * Stack middleware de base : auth → verified → staff (EnsureIsStaff)
 */

Route::middleware(['auth', 'verified', 'staff'])->prefix('admin')->name('admin.')->group(function () {

    // ─── ROUTES STAFF (Accès Opérateurs & Marketing) ──────────────────────

    // GET /admin/dashboard — KPIs + file d'attente des commandes PENDING
    Volt::route('/dashboard', 'pages.admin.dashboard')
        ->name('dashboard');

    // GET /admin/transparency — Dashboard de transparence salariale (Loi UE)
    Volt::route('/transparency', 'pages.admin.transparency.index')
        ->name('transparency.index');

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

    // GET /admin/orders/{order}/invoice — Télécharger la facture PDF
    Route::get('/orders/{order}/invoice',
        [\App\Http\Controllers\Admin\AdminInvoiceController::class, 'download']
    )->name('orders.invoice');

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

    // ─── Modération IA ────────────────────────────────────────────────────
    Volt::route('/moderation/lexicon', 'pages.admin.moderation.lexicon')
        ->name('moderation.lexicon');


    // ─── ROUTES SUPER-ADMIN (Pilotage & Finances) ─────────────────────────
    Route::middleware(['admin'])->group(function () {

        // ─── Compliance / Légal ───────────────────────────────────────────────
        // GET /admin/compliance — Rappels légaux (RGPD, NIS2) pour l'admin
        Volt::route('/compliance', 'pages.admin.compliance')
            ->name('compliance');

        // ─── Chiffre d'Affaire ───────────────────────────────────────────────
        // GET /admin/revenue — CA mensuel avec graphes Chart.js
        Volt::route('/revenue', 'pages.admin.revenue.index')
            ->name('revenue');

        // GET /admin/revenue/simulation — Simulateur d'objectifs (2 personnes)
        Volt::route('/revenue/simulation', 'pages.admin.revenue.simulation')
            ->name('revenue.simulation');

        Route::get('/revenue/export',
            \App\Http\Controllers\Admin\AdminRevenueExportController::class . '@download'
        )->name('revenue.export');

        // ─── Cellule de Crise (PRI) ──────────────────────────────────────────
        // Poste de commandement en cas d'incident majeur ou RGPD
        Volt::route('/incident-response', 'pages.admin.incident.index')
            ->name('incident.response');

        Route::get('/incident-response/export',
            \App\Http\Controllers\Admin\IncidentReportController::class . '@download'
        )->name('incident.export');

    });

});

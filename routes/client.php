<?php

use App\Http\Controllers\Client\OrderDownloadController;
use Illuminate\Support\Facades\Route;

/**
 * Client Routes — OmnyRestore
 *
 * All routes in this file are automatically loaded by the route service provider.
 * Include this file in bootstrap/app.php or routes/web.php as:
 *   require __DIR__.'/client.php';
 *
 * Middleware stack applied to ALL client routes:
 *   - auth      → User must be logged in (redirects to /login if not)
 *   - verified  → Email must be verified (redirects to /verify-email if not)
 *
 * These routes use Route Model Binding:
 *   {order} → Laravel automatically fetches Order::find($id) and injects it.
 *   If the order doesn't exist → 404. The Policy checks ownership after.
 *
 * IMPORTANT: Policy checks (IDOR prevention) happen inside each controller.
 * The middleware only ensures authentication and email verification.
 */

Route::middleware(['auth', 'verified'])->prefix('client')->name('client.')->group(function () {

    // ─── Orders ───────────────────────────────────────────────────────────
    // Volt::route() active le cycle de vie complet du composant Livewire/Volt.

    // GET /client/orders
    \Livewire\Volt\Volt::route('/orders', 'pages.client.orders.index')
        ->name('orders.index');

    // GET /client/orders/create
    \Livewire\Volt\Volt::route('/orders/create', 'pages.client.orders.create')
        ->name('orders.create');

    // GET /client/orders/{order} — Route Model Binding → Order::find($id)
    \Livewire\Volt\Volt::route('/orders/{order}', 'pages.client.orders.show')
        ->name('orders.show');

    // POST /client/orders/{order}/checkout → Stripe Checkout
    Route::post('/orders/{order}/checkout',
        \App\Http\Controllers\Client\OrderCheckoutController::class . '@checkout'
    )->name('orders.checkout');

    // GET /client/orders/{order}/download → S3 presigned URL
    Route::get('/orders/{order}/download', [OrderDownloadController::class, 'download'])
         ->name('orders.download');

    // ─── Profile / RGPD ───────────────────────────────────────────────────
    // GET /client/profile
    \Livewire\Volt\Volt::route('/profile', 'pages.client.profile')
        ->name('profile');

});



<?php

use Illuminate\Support\Facades\Route;

/**
 * Admin Routes — OmnyRestore
 *
 * Back-office routes for administrators only.
 *
 * Middleware stack:
 *   - auth    → Must be logged in
 *   - verified → Email must be verified
 *   - admin   → Must have role = 'admin' (EnsureIsAdmin middleware alias)
 *
 * All admin routes are prefixed with /admin and named with admin.*
 * This makes it trivial to restrict in middleware and generate URLs.
 *
 * Route Model Binding is used throughout: {order} → Order::find($id)
 * The admin middleware's before() in OrderPolicy allows admins to access any order.
 */

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {

    // ─── Dashboard ────────────────────────────────────────────────────────

    // Admin dashboard: KPIs + pending order queue
    // GET /admin/dashboard
    Route::get('/dashboard', function () {
        return view('livewire.pages.admin.dashboard');
    })->name('dashboard');

    // ─── Order Management ─────────────────────────────────────────────────

    // List all orders (all statuses, filterable)
    // GET /admin/orders
    Route::get('/orders', function () {
        return view('livewire.pages.admin.orders.index');
    })->name('orders.index');

    // Show and manage a specific order
    // GET /admin/orders/{order}
    Route::get('/orders/{order}', function () {
        return view('livewire.pages.admin.orders.show');
    })->name('orders.show');

    // Update order status (AJAX/Livewire PATCH)
    // PATCH /admin/orders/{order}/status
    Route::patch('/orders/{order}/status',
        \App\Http\Controllers\Admin\OrderController::class . '@updateStatus'
    )->name('orders.status');

    // ─── Horizon Dashboard (Queue Monitoring) ─────────────────────────────
    // Laravel Horizon provides a real-time dashboard for queue monitoring.
    // It's already registered by HorizonServiceProvider with its own auth gate.
    // Access: /horizon (protected by HorizonServiceProvider::gate())

});

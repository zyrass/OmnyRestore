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

    // List all orders for the authenticated client
    // GET /client/orders
    Route::get('/orders', function () {
        // Handled by Livewire component OrderList
        return view('livewire.pages.client.orders.index');
    })->name('orders.index');

    // Show the form to create a new order
    // GET /client/orders/create
    Route::get('/orders/create', function () {
        return view('livewire.pages.client.orders.create');
    })->name('orders.create');

    // Show a specific order (detail + watermarked preview)
    // GET /client/orders/{order}
    Route::get('/orders/{order}', function () {
        return view('livewire.pages.client.orders.show');
    })->name('orders.show');

    // Initiate Stripe Checkout for an order
    // POST /client/orders/{order}/checkout
    Route::post('/orders/{order}/checkout',
        \App\Http\Controllers\Client\OrderCheckoutController::class . '@checkout'
    )->name('orders.checkout');

    // Secure download: verify payment → generate presigned URL → redirect to S3
    // GET /client/orders/{order}/download
    Route::get('/orders/{order}/download', [OrderDownloadController::class, 'download'])
         ->name('orders.download');

    // ─── Profile / GDPR ───────────────────────────────────────────────────

    // Profile settings (GDPR: data export, account deletion)
    // GET /client/profile
    Route::get('/profile', function () {
        return view('profile');
    })->name('profile');

});

/**
 * Stripe Success/Cancel redirect routes (public — no auth required)
 * Stripe redirects here after Checkout Session completion.
 * We do NOT rely on these for payment confirmation — use the webhook instead.
 */
Route::get('/payment/success', function () {
    // Show a "Thank you, we'll email you your download link" page
    return view('pages.payment.success');
})->name('payment.success');

Route::get('/payment/cancel', function () {
    // Show a "Payment cancelled, your order is still available" page
    return view('pages.payment.cancel');
})->name('payment.cancel');

<?php

use App\Http\Controllers\Client\OrderDownloadController;
use App\Models\OrderDelivery;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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

Route::middleware(['auth', 'verified', 'client', 'throttle:360,1'])->prefix('client')->name('client.')->group(function () {

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

    // GET /client/orders/{order}/payment-success — Page confirmation post-Stripe
    \Livewire\Volt\Volt::route('/orders/{order}/payment-success', 'pages.client.orders.payment-success')
        ->name('orders.payment-success');

    // POST /client/orders/{order}/checkout → Stripe Checkout
    // Throttle : 10 tentatives par minute par utilisateur.
    // Prévient les doubles-clics, les scripts automatisés, et les abus de session.
    Route::post('/orders/{order}/checkout',
        \App\Http\Controllers\Client\OrderCheckoutController::class . '@checkout'
    )->name('orders.checkout')->middleware('throttle:30,1');

    // GET /client/orders/{order}/download → S3 presigned URL (ou Laravel signed URL local)
    Route::get('/orders/{order}/download', [OrderDownloadController::class, 'download'])
         ->name('orders.download');

    // GET /client/orders/{order}/invoice → Télécharger la facture PDF (commande payée uniquement)
    Route::get('/orders/{order}/invoice', [\App\Http\Controllers\Client\InvoiceController::class, 'download'])
         ->name('orders.invoice');

    // GET /client/orders/{order}/photos/{media} — Sert une photo retouchée de façon SÉCURISÉE
    // ⚠️ NE PAS exposer via /storage/ : les fichiers retouched sont sur le disk 'local' (privé).
    // Ce controller vérifie : auth + propriété commande + preview_unlocked_at + non rejetée.
    Route::get('/orders/{order}/photos/{media}', [\App\Http\Controllers\Client\SecurePhotoController::class, 'show'])
         ->name('orders.photo.show');


    // GET /client/orders/download/stream/{delivery} → Stream local ZIP (dev only, URL signée)
    Route::get('/orders/download/stream/{delivery}', function (\Illuminate\Http\Request $request, OrderDelivery $delivery) {
        // Vérification signature + auth (la signed URL garantit l'authenticité)
        abort_unless($request->hasValidSignature(), 403);
        abort_unless(
            $delivery->order->user_id === $request->user()->id,
            403
        );
        $path = storage_path('app/' . $delivery->zip_path);
        abort_unless(file_exists($path), 404, 'Archive introuvable.');
        return response()->download($path, basename($path), [
            'Content-Type' => 'application/zip',
        ]);
    })->name('orders.download.stream')->middleware(['auth', 'verified']);


    // ─── Profile / RGPD ───────────────────────────────────────────────────
    // GET /client/profile
    \Livewire\Volt\Volt::route('/profile', 'pages.client.profile')
        ->name('profile');

    // GET /client/account/delete — Suppression de compte RGPD Art. 17
    // Volt::route pointe vers livewire/pages/client/account/delete.blade.php
    \Livewire\Volt\Volt::route('/account/delete', 'pages.client.account.delete')
        ->name('account.delete');

    // GET /client/account/export — Portabilité des données RGPD Art. 20
    Route::get('/account/export', [\App\Http\Controllers\Client\ExportUserDataController::class, 'export'])
        ->name('account.export');

    // ─── Support Tickets ──────────────────────────────────────────────────
    // GET  /client/tickets         → Liste des tickets
    \Livewire\Volt\Volt::route('/tickets', 'pages.client.tickets.index')
        ->name('tickets.index');

    // GET  /client/tickets/create  → Nouveau ticket (avec sélection commande)
    \Livewire\Volt\Volt::route('/tickets/create', 'pages.client.tickets.create')
        ->name('tickets.create');

    // GET  /client/tickets/{ticket} → Fil de conversation
    \Livewire\Volt\Volt::route('/tickets/{ticket}', 'pages.client.tickets.show')
        ->name('tickets.show');

});



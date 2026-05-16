<?php

use App\Http\Middleware\EnsureIsAdmin;
use App\Http\Middleware\EnsureIsClient;
use App\Http\Middleware\EnsureIsStaff;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ─── En-têtes de sécurité HTTP ─────────────────────────────────────────
        // Appliqué sur toutes les routes web : CSP, HSTS, X-Frame-Options, etc.
        // Objectif : Grade A sur securityheaders.com en production.
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        // ─── Alias de middleware ────────────────────────────────────────────────
        // Raccourcis utilisés dans les définitions de routes.
        // Usage : Route::middleware(['auth', 'verified', 'admin'])->group(...)
        $middleware->alias([
            'admin'  => EnsureIsAdmin::class,
            'client' => EnsureIsClient::class,
            'staff'  => EnsureIsStaff::class,
        ]);

        // ─── Exemptions CSRF ────────────────────────────────────────────────────
        // Les webhooks Stripe sont des requêtes POST depuis les serveurs Stripe.
        // Ils ne peuvent pas inclure de token CSRF — cette route est exemptée.
        // La sécurité est assurée par la vérification de signature HMAC-SHA256.
        $middleware->validateCsrfTokens(except: [
            '/webhook/stripe',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

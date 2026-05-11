<?php

use App\Http\Middleware\EnsureIsAdmin;
use App\Http\Middleware\EnsureIsClient;
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
        // ─── Middleware Aliases ────────────────────────────────────────────
        // Register shorthand aliases for middleware used in route definitions.
        // Usage in routes: Route::middleware(['auth', 'verified', 'admin'])->...
        $middleware->alias([
            'admin' => EnsureIsAdmin::class,
            'client' => EnsureIsClient::class,
        ]);

        // ─── CSRF Exemptions ───────────────────────────────────────────────
        // Stripe webhooks are POST requests from Stripe's servers.
        // They cannot include a CSRF token — exempt this route from verification.
        // Security is maintained by Stripe's HMAC-SHA256 signature verification.
        $middleware->validateCsrfTokens(except: [
            '/webhook/stripe',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

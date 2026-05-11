<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: EnsureIsAdmin
 *
 * Restricts access to admin routes. Applied to the /admin/* route group.
 *
 * Usage in routes/admin.php:
 *   Route::middleware(['auth', 'verified', 'admin'])->group(function () { ... });
 *
 * Registration in bootstrap/app.php:
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->alias(['admin' => EnsureIsAdmin::class]);
 *   })
 *
 * Why not use Gates/Policies here?
 *   - This is a route-level guard: deny entry BEFORE hitting any controller.
 *   - Policies handle record-level authorization (e.g., "can this user view THIS order?").
 *   - Middleware handles broad access control (e.g., "can this user enter the admin area?").
 *
 * Security note:
 *   This middleware assumes auth middleware runs FIRST (user is already authenticated).
 *   Always chain: auth → verified → admin (in that order).
 */
class EnsureIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Double-check authentication (defensive programming)
        // The 'auth' middleware should have already run, but we verify here too
        // in case this middleware is accidentally used without 'auth'.
        if (! $request->user()) {
            return redirect()->route('login');
        }

        // Check if the authenticated user has the 'admin' role.
        // isAdmin() is defined on the User model: return $this->role === 'admin'
        if (! $request->user()->isAdmin()) {
            // Return 403 Forbidden — not a redirect to avoid leaking admin URL existence.
            // The client sees "This action is unauthorized." from Laravel's default handler.
            abort(403, 'Access restricted to administrators.');
        }

        // User is authenticated and is an admin — allow the request through.
        return $next($request);
    }
}

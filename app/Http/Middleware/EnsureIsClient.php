<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: EnsureIsClient
 *
 * Protège les routes `/client/*` contre l'accès par les administrateurs.
 *
 * Un admin connecté qui tente d'accéder à `/client/orders` est redirigé
 * vers `/admin/dashboard` plutôt que d'obtenir un 403 — expérience UX propre.
 *
 * Chaîne middleware conseillée pour les routes client :
 *   auth → verified → client
 */
class EnsureIsClient
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        // Un admin n'a pas accès à l'espace client → redirect propre vers le dashboard admin
        if ($request->user()->isAdmin()) {
            return redirect()
                ->route('admin.dashboard')
                ->with('info', 'Vous êtes connecté en tant qu\'administrateur.');
        }

        return $next($request);
    }
}

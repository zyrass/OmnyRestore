<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsStaff
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $forbiddenRole = null): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if ($request->user()->isSuspended()) {
            abort(403, 'Votre compte a été suspendu par un administrateur.');
        }

        if (! $request->user()->isStaff()) {
            abort(403, 'Accès réservé aux membres de l\'équipe (Staff).');
        }

        if ($forbiddenRole && $request->user()->role === $forbiddenRole) {
            abort(403, 'Vous n\'avez pas les privilèges suffisants pour accéder à cette ressource.');
        }

        return $next($request);
    }
}

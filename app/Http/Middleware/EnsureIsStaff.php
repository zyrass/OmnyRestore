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
    public function handle(Request $request, Closure $next): Response
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

        return $next($request);
    }
}

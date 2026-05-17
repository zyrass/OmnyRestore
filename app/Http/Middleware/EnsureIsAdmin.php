<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware : EnsureIsAdmin
 *
 * Restreint l'accès aux routes d'administration. Appliqué au groupe /admin/*.
 *
 * Utilisation dans routes/admin.php :
 *   Route::middleware(['auth', 'verified', 'admin'])->group(function () { ... });
 *
 * Enregistrement dans bootstrap/app.php :
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->alias(['admin' => EnsureIsAdmin::class]);
 *   })
 *
 * Pourquoi ne pas utiliser les Gates/Policies ici ?
 *   - Ce middleware est une protection au niveau de la route : refus d'accès AVANT le contrôleur.
 *   - Les Policies gèrent l'autorisation au niveau de l'enregistrement (ex : "cet user peut-il voir CETTE commande ?").
 *   - Le middleware gère le contrôle d'accès global (ex : "cet user peut-il entrer dans l'espace admin ?").
 *
 * Note de sécurité :
 *   Ce middleware suppose que le middleware 'auth' s'est exécuté EN PREMIER (utilisateur déjà authentifié).
 *   Respecter l'ordre : auth → verified → admin.
 */
class EnsureIsAdmin
{
    /**
     * Traite la requête entrante.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Vérification défensive de l'authentification.
        // Le middleware 'auth' devrait avoir déjà été exécuté, mais on vérifie ici
        // au cas où ce middleware serait utilisé accidentellement sans 'auth'.
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if ($request->user()->isSuspended()) {
            abort(403, 'Votre compte a été suspendu par un administrateur.');
        }

        // Vérifie que l'utilisateur authentifié possède le rôle 'admin'.
        // isAdmin() est défini sur le modèle User : return $this->role === 'admin'
        if (! $request->user()->isAdmin()) {
            // Retourne 403 Forbidden — pas de redirection pour ne pas révéler l'existence des URLs admin.
            // L'utilisateur voit "This action is unauthorized." via le gestionnaire d'erreurs Laravel.
            abort(403, 'Accès réservé aux administrateurs.');
        }

        // L'utilisateur est authentifié et est admin — la requête est transmise.
        return $next($request);
    }
}

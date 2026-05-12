<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur de déverrouillage de l'aperçu.
 *
 * Route : GET /unlock-preview/{order}?signature=...&expires=...
 * Middleware : signed (Laravel vérifie la signature automatiquement)
 * Auth : non requise — le middleware `auth` sur la page show prend le relais.
 *
 * Flux :
 *   1. Le client reçoit l'email OrderReadyForPayment avec une URL signée (7 jours).
 *   2. Il clique → ce contrôleur valide la signature et marque preview_unlocked_at.
 *   3. Il est redirigé vers client.orders.show.
 *   4. Si non connecté → auth middleware redirige vers /login avec intended URL.
 *   5. Après connexion → il arrive sur la page commande où l'aperçu est débloqué.
 */
class UnlockPreviewController extends Controller
{
    public function __invoke(Request $request, Order $order): RedirectResponse
    {
        // La signature est déjà validée par le middleware `signed`.
        // On ne vérifie pas l'identité ici : la page show le fait via Policy.

        // Idempotent : si déjà débloqué, on redirige simplement sans double-écriture.
        if (! $order->preview_unlocked_at) {
            $order->update(['preview_unlocked_at' => now()]);
        }

        return redirect()
            ->to(route('client.orders.show', $order))
            ->with('success', '✨ Vos photos restaurées sont maintenant accessibles !');
    }
}

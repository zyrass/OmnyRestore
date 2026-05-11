<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\AutoRestoreOrderPhotosJob;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller: OrderAutoRestoreController
 *
 * Déclenche la restauration IA automatique d'une commande (Phase 8).
 *
 * Route: POST /admin/orders/{order}/auto-restore
 * Middleware: auth, verified, admin
 *
 * Sécurité :
 *   - Accessible uniquement aux admins (middleware 'admin')
 *   - Vérifie que la commande a des photos originales
 *   - Vérifie que le statut permet la restauration (PENDING ou IN_PROGRESS)
 */
class OrderAutoRestoreController
{
    /**
     * Dispatch le job de restauration IA.
     */
    public function dispatch(Request $request, Order $order): RedirectResponse
    {
        // Vérifications préalables
        if (! in_array($order->status, ['PENDING', 'IN_PROGRESS'])) {
            return back()->with('error', "La restauration IA n'est possible que sur les commandes PENDING ou IN_PROGRESS (statut actuel : {$order->status}).");
        }

        $originalCount = $order->getMedia('originals')->count();
        if ($originalCount === 0) {
            return back()->with('error', 'Aucune photo originale trouvée sur cette commande.');
        }

        if (! config('openai.api_key')) {
            return back()->with('error', 'Clé API OpenAI non configurée (OPENAI_API_KEY manquante dans .env).');
        }

        // Dispatch en arrière-plan
        AutoRestoreOrderPhotosJob::dispatch($order);

        return back()->with('success', "🤖 Restauration IA lancée pour {$originalCount} photo(s) — résultats disponibles dans quelques minutes.");
    }
}

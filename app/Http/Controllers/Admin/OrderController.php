<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controller : OrderController (Admin)
 *
 * Gère les actions d'administration sur les commandes non couvertes par les composants Livewire.
 * L'interface Livewire principale est dans : resources/views/livewire/pages/admin/orders/
 *
 * Ce contrôleur gère les transitions d'état via des requêtes PATCH (style API REST).
 * Le middleware 'admin' (EnsureIsAdmin) est appliqué au niveau du groupe de routes.
 *
 * @see App\Http\Middleware\EnsureIsAdmin
 * @see routes/admin.php
 *
 * TODO Phase 2 : Gestion complète des commandes admin via composants Livewire
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Met à jour le statut d'une commande.
     *
     * Appelé par : PATCH /admin/orders/{order}/status
     * Middleware : auth + verified + admin (depuis le groupe de routes)
     *
     * Valide le nouveau statut et utilise les méthodes de la machine d'état du modèle Order
     * pour garantir des transitions valides (guardTransition empêche les transitions illégales).
     *
     * @param Request $request
     * @param Order   $order Lié automatiquement par Route Model Binding
     * @return RedirectResponse
     */
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $previousStatus = $order->status;
        $newStatus      = $validated['status'];

        try {
            match ($newStatus) {
                'IN_PROGRESS' => $order->startProcessing(),
                'DONE'        => $order->markAsDone(),
                'CANCELLED'   => $order->cancel($validated['reason'] ?? ''),
                default       => throw new \InvalidArgumentException("Unsupported status: {$newStatus}"),
            };
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Journalisation du changement de statut dans le log d'audit
        $this->auditService->orderStatusChanged($order, $previousStatus, $newStatus);

        return back()->with('success', "Commande {$order->reference} : statut mis à jour → {$newStatus}");
    }
}

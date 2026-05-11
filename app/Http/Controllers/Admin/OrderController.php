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
 * Controller: OrderController (Admin)
 *
 * Handles admin order management actions not covered by Livewire components.
 * The primary Livewire UI is in: resources/views/livewire/pages/admin/orders/
 *
 * This controller handles API-style state transitions triggered via PATCH requests.
 * The 'admin' middleware (EnsureIsAdmin) is applied at the route group level.
 *
 * @see App\Http\Middleware\EnsureIsAdmin
 * @see routes/admin.php
 *
 * TODO Phase 2: Full admin order management via Livewire components
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * Update the status of an order.
     *
     * Called by: PATCH /admin/orders/{order}/status
     * Middleware: auth + verified + admin (from route group)
     *
     * Validates the new status and uses the Order model's state machine methods
     * to enforce valid transitions (guardTransition prevents invalid moves).
     *
     * @param Request $request
     * @param Order   $order Route model binding
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

        // Record state change in audit log
        $this->auditService->orderStatusChanged($order, $previousStatus, $newStatus);

        return back()->with('success', "Commande {$order->reference} : statut mis à jour → {$newStatus}");
    }
}

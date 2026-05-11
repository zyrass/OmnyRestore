<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * OrderPolicy — OmnyRestore
 *
 * Defines authorization rules for Order model operations.
 * This is the primary IDOR (Insecure Direct Object Reference) prevention layer.
 *
 * IDOR vulnerability example WITHOUT this policy:
 *   Client A knows their order is at /client/orders/uuid-a
 *   They manually change the URL to /client/orders/uuid-b
 *   WITHOUT a policy: they would see client B's private photos!
 *   WITH this policy: Laravel returns 403 before any data is fetched.
 *
 * Usage in controllers:
 *   $this->authorize('view', $order);      // Throws 403 if not authorized
 *   $this->authorize('update', $order);
 *
 * Usage in Livewire components:
 *   Gate::authorize('view', $order);
 *
 * Registration: Automatic via convention (Order model → OrderPolicy).
 * Laravel auto-discovers policies following the App\Policies\ namespace convention.
 *
 * @see https://laravel.com/docs/authorization#creating-policies
 */
class OrderPolicy
{
    use HandlesAuthorization;

    /**
     * Allow admins to bypass ALL policy checks.
     *
     * This method runs BEFORE any other method in this policy.
     * If it returns true, the check passes immediately.
     * If it returns null, the specific method is evaluated.
     *
     * Admins can view, update, and manage ALL orders on the platform.
     */
    public function before(User $user, string $ability): ?bool
    {
        // Admins bypass all order policy checks
        if ($user->isAdmin()) {
            return true;
        }

        // Return null to fall through to the specific policy method
        return null;
    }

    /**
     * Determine if the user can view any orders.
     *
     * Used for the order list page (/client/orders).
     * Both clients and admins can access their respective list pages.
     * The actual data filtering (admin sees all, client sees own) is done in queries.
     */
    public function viewAny(User $user): bool
    {
        // Any authenticated user can view the order list
        // (filtered to their own orders in the controller/Livewire component)
        return true;
    }

    /**
     * Determine if the user can view a specific order.
     *
     * This is the core IDOR prevention: a client can only view THEIR OWN orders.
     * The 'before()' method already handles admin access above.
     *
     * @param User  $user  The authenticated user attempting access
     * @param Order $order The order being requested
     */
    public function view(User $user, Order $order): bool
    {
        // Client can only see their own orders
        // UUID comparison: $order->user_id must match the authenticated user's id
        return $user->id === $order->user_id;
    }

    /**
     * Determine if the user can create a new order.
     *
     * Any verified client can create orders.
     * Admin could also create orders (e.g., on behalf of a client) — allowed via before().
     */
    public function create(User $user): bool
    {
        // Clients can create orders (email verification enforced by middleware)
        return $user->isClient();
    }

    /**
     * Determine if the user can update an order.
     *
     * Clients can only update PENDING orders (e.g., edit description before admin picks up).
     * Admins can update any order (handled by before()).
     */
    public function update(User $user, Order $order): bool
    {
        // Client can only update their own PENDING orders
        return $user->id === $order->user_id
            && $order->status === 'PENDING';
    }

    /**
     * Determine if the user can download the ZIP for an order.
     *
     * This is the payment gate: download is only allowed AFTER successful payment.
     * Even if a client somehow gets a direct link to /download, this check stops them.
     *
     * Conditions for download:
     *   1. User owns the order (IDOR prevention)
     *   2. payment_status is 'paid' (Stripe confirmed payment)
     *   3. Order has a delivery record (ZIP was generated)
     */
    public function download(User $user, Order $order): bool
    {
        return $user->id === $order->user_id        // Must own the order
            && $order->payment_status === 'paid'    // Must have paid
            && $order->delivery !== null;           // ZIP must exist
    }

    /**
     * Determine if the user can delete an order.
     *
     * Clients can cancel (soft-delete) their own PENDING orders.
     * Admins can cancel/delete any order (handled by before()).
     */
    public function delete(User $user, Order $order): bool
    {
        return $user->id === $order->user_id
            && $order->status === 'PENDING';
    }
}

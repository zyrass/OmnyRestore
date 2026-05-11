<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Configure Horizon alert notifications (Phase 2 — uncomment and configure):
        // Horizon::routeMailNotificationsTo('admin@omnyrestore.fr');
        // Horizon::routeSlackNotificationsTo(env('HORIZON_SLACK_WEBHOOK'), '#ops');
    }

    /**
     * Register the Horizon gate.
     *
     * Controls who can access the /horizon dashboard.
     * In local environment: always accessible (no auth required in development).
     * In staging/production: restricted to users with role = 'admin'.
     *
     * Security note: This gate runs on top of web auth middleware.
     * The user must ALSO be authenticated via the standard auth system.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null): bool {
            // Allow access only to users with the 'admin' role.
            // $user may be null if accessed unauthenticated — deny in that case.
            return $user !== null && $user->isAdmin();
        });
    }
}

<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    /**
     * Bootstrap application services.
     *
     * - Règles de mot de passe CNIL (12 chars, mixedCase, numbers, symbols, HaveIBeenPwned)
     * - Enregistrement de l'OrderObserver (emails transactionnels automatiques)
     */
    public function boot(): void
    {
        // ── Politique de mot de passe CNIL ──────────────────────────────────
        // @see https://www.cnil.fr/fr/mots-de-passe-les-recommandations-de-la-cnil
        Password::defaults(function () {
            return Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised();
        });

        // ── Observer Eloquent ────────────────────────────────────────────────
        // Écoute les changements de statut Order pour envoyer les emails
        // (OrderReadyForPayment quand DONE, OrderPaidConfirmation quand PAID)
        Order::observe(OrderObserver::class);
    }
}


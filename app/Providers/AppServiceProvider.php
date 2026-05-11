<?php

namespace App\Providers;

use App\Listeners\GenerateWatermarkOnRetouchedUpload;
use App\Models\Order;
use App\Observers\OrderObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    /**
     * Bootstrap application services.
     *
     * - Règles de mot de passe CNIL (12 chars, mixedCase, numbers, symbols, HaveIBeenPwned)
     * - Enregistrement de l'OrderObserver (emails transactionnels automatiques)
     * - Listener Spatie : génération watermark automatique sur upload `retouched`
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

        // ── Watermark automatique ────────────────────────────────────────────
        // Quand l'admin uploade une photo dans `retouched`, Spatie déclenche cet event.
        // Le listener dispatch GenerateWatermarkJob en arrière-plan (queue).
        Event::listen(
            MediaHasBeenAddedEvent::class,
            GenerateWatermarkOnRetouchedUpload::class
        );
    }
}

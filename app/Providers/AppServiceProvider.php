<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── OpenAI Client — SSL Certificate Fix (Windows / cURL error 60) ──
        // PHP sur Windows n'a pas de CA bundle par défaut.
        // On surcharge le binding OpenAI\Client pour lui passer un client
        // Guzzle configuré avec notre cacert.pem local.
        // Le fichier vient de https://curl.se/ca/cacert.pem (storage/cacert.pem)
        $this->app->singleton(\OpenAI\Client::class, function () {
            $caCert   = storage_path('cacert.pem');
            $guzzle   = new \GuzzleHttp\Client([
                'verify'  => file_exists($caCert) ? $caCert : true,
                'timeout' => (int) config('openai.request_timeout', 60),
            ]);

            return \OpenAI::factory()
                ->withApiKey(config('openai.api_key'))
                ->withOrganization(config('openai.organization'))
                ->withHttpClient($guzzle)
                ->make();
        });
    }

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
        // NB: Le listener GenerateWatermarkOnRetouchedUpload est enregistré
        // AUTOMATIQUEMENT par l'auto-découverte Laravel 11 (app/Listeners/).
        // Ne PAS l'enregistrer manuellement ici → évite le double dispatch.

        // ── Personnalisation de l'email de vérification (RGPD / Marque) ──────
        \Illuminate\Auth\Notifications\VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Vérifiez votre adresse email — OmnyRestore')
                ->view('emails.auth.verify-email', ['url' => $url, 'user' => $notifiable]);
        });
    }
}

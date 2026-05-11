<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    /**
     * Bootstrap application services.
     *
     * Définit les règles de mot de passe par défaut conformément aux recommandations
     * de la CNIL (Guide sécurité des données personnelles — Fiche 5) :
     *
     * Recommandations CNIL 2023 (sans dispositif complémentaire) :
     *   - Minimum 12 caractères
     *   - Au moins 1 lettre majuscule
     *   - Au moins 1 lettre minuscule
     *   - Au moins 1 chiffre
     *   - Au moins 1 caractère spécial
     *
     * Ces règles s'appliquent automatiquement partout où on utilise
     * Rules\Password::defaults() dans les validations.
     *
     * @see https://www.cnil.fr/fr/mots-de-passe-les-recommandations-de-la-cnil
     * @see https://www.cnil.fr/sites/cnil/files/atoms/files/cnil_guide_securite-donnees-personnelles.pdf
     */
    public function boot(): void
    {
        Password::defaults(function () {
            return Password::min(12)          // CNIL : 12 caractères minimum
                ->mixedCase()                 // Majuscules + minuscules
                ->numbers()                   // Au moins un chiffre
                ->symbols()                   // Au moins un caractère spécial
                ->uncompromised();            // Vérifie via HaveIBeenPwned API
                                              // (rejette les mots de passe dans des fuites connues)
        });
    }
}

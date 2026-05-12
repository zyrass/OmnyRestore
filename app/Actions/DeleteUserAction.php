<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * DeleteUserAction — Anonymisation RGPD complète d'un compte client
 *
 * Exécuté quand le client demande la suppression de son compte (RGPD Art. 17).
 *
 * CE QUI EST SUPPRIMÉ :
 *   - Toutes les photos (originales + restaurées) via Spatie Media Library
 *   - Tous les tickets de support et leurs messages
 *   - Les données identifiantes : nom, email (anonymisés), mot de passe, Stripe ID
 *   - Le compte lui-même (soft-delete : deleted_at est renseigné)
 *
 * CE QUI EST CONSERVÉ (obligations légales) :
 *   - Les commandes et factures (10 ans — Art. L.123-22 Code de commerce)
 *     → mais le user_id pointe vers un profil anonymisé (plus de PII accessible)
 *   - L'enregistrement users avec anonymized_at (audit trail RGPD)
 *   - rgpd_consent_at (preuve du consentement initial)
 *
 * SÉCURITÉ :
 *   - Le mot de passe est vérifié AVANT toute action irréversible
 *   - L'anonymisation est IRRÉVERSIBLE — la méthode ne peut être rappelée sur un même user
 *   - Les sessions sont invalidées par le composant appelant (hors scope de l'action)
 *
 * @throws \InvalidArgumentException si le mot de passe est incorrect
 * @throws \LogicException si le compte est déjà anonymisé
 */
class DeleteUserAction
{
    public function execute(User $user, string $password): void
    {
        // ── Garde-fous ──────────────────────────────────────────────────────
        if ($user->anonymized_at) {
            throw new \LogicException('Ce compte a déjà été anonymisé.');
        }

        if (! Hash::check($password, $user->password)) {
            throw new \InvalidArgumentException('Mot de passe incorrect. Veuillez réessayer.');
        }

        Log::info("DeleteUserAction: début anonymisation pour user={$user->id}");

        // ── 1. Supprimer toutes les photos (Spatie Media Library) ──────────
        $orderIds = Order::where('user_id', $user->id)->pluck('id');

        Order::whereIn('id', $orderIds)->each(function (Order $order) {
            try {
                $order->clearMediaCollection('originals');
                $order->clearMediaCollection('retouched');
                $order->clearMediaCollection('watermarked');
            } catch (\Throwable $e) {
                Log::warning("DeleteUserAction: échec suppression média pour order={$order->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        });

        Log::info("DeleteUserAction: médias supprimés pour {$orderIds->count()} commande(s)");

        // ── 2. Supprimer les tickets de support ────────────────────────────
        // (Aucune obligation légale de conserver les messages de support)
        if (method_exists($user, 'supportTickets')) {
            $user->supportTickets->each(function ($ticket) {
                if (method_exists($ticket, 'messages')) {
                    $ticket->messages()->delete();
                }
                $ticket->delete();
            });
        }

        // ── 3. Anonymiser les données identifiantes ────────────────────────
        // L'email anonymisé utilise un hash court de l'UUID (non-réversible).
        // Le format "deleted_{hash}@data.deleted" garantit :
        //   - Unicité (pas de collision si l'UUID est unique)
        //   - Non-interprétable comme un vrai domaine (@data.deleted = invalide)
        //   - Conformité avec les contraintes UNIQUE de la colonne email
        $shortHash = substr(md5($user->id), 0, 16);

        $user->forceFill([
            'name'               => 'Utilisateur supprimé',
            'email'              => "deleted_{$shortHash}@data.deleted",
            'password'           => Hash::make(Str::random(64)), // invalide, indevinable
            'remember_token'     => null,
            'stripe_id'          => null,              // Customer Stripe dissocie
            'pm_type'            => null,              // Cashier
            'pm_last_four'       => null,              // Cashier
            'marketing_consent'  => false,
            'email_verified_at'  => null,
            'anonymized_at'      => now(),
            // rgpd_consent_at est CONSERVÉ (preuve du consentement — RGPD Art. 7.1)
        ])->save();

        // ── 4. Soft-delete (deleted_at) ─────────────────────────────────────
        // Le soft-delete exclut l'utilisateur de toutes les requêtes normales.
        // L'enregistrement reste en base (nécessaire pour les FK des commandes).
        $user->delete();

        Log::info("DeleteUserAction: compte user={$user->id} anonymisé et supprimé (soft-delete)", [
            'anonymized_at' => $user->anonymized_at,
            'orders_kept'   => $orderIds->count(),
        ]);
    }
}

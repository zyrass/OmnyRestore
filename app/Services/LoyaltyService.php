<?php

namespace App\Services;

use App\Models\User;
use App\Models\Coupon;
use App\Mail\LoyaltyRewardEarned;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * LoyaltyService — Système de fidélisation gamifié.
 *
 * Implémente la boucle de fidélité : 3 commandes éligibles (>= 10€ TTC payées)
 * déclenchent l'obtention d'un bon de réduction de 50% (valable 1 mois).
 *
 * Ce service fonctionne selon un modèle d'auto-correction (Self-Healing)
 * pour éviter les désynchronisations de compteurs.
 */
class LoyaltyService
{
    /**
     * Calcule la fidélité, génère les coupons manquants et notifie le client.
     */
    public function checkAndReward(User $user): void
    {
        $eligibleOrdersCount = $user->eligibleOrdersCount();
        
        // Un bon gagné toutes les 3 commandes éligibles
        $theoreticalCoupons = (int) floor($eligibleOrdersCount / 3);
        
        // Nombre de coupons de fidélité réels dans la base pour cet utilisateur
        $actualCoupons = $user->coupons()
            ->where('is_loyalty', true)
            ->count();

        $couponsToCreate = $theoreticalCoupons - $actualCoupons;

        if ($couponsToCreate > 0) {
            Log::info("Loyalty reward check: generating {$couponsToCreate} coupon(s) for user {$user->id} (Eligible orders: {$eligibleOrdersCount})");
            
            for ($i = 0; $i < $couponsToCreate; $i++) {
                $code = 'FID50-' . strtoupper(Str::random(6));

                // Éviter les collisions de code rares
                while (Coupon::where('code', $code)->exists()) {
                    $code = 'FID50-' . strtoupper(Str::random(6));
                }

                $coupon = Coupon::create([
                    'user_id'         => $user->id,
                    'code'            => $code,
                    'description'     => 'Fidélité — 50% sur votre 4ème commande',
                    'type'            => 'percentage',
                    'value'           => 50,
                    'min_order_cents' => 0, // Pas de minimum requis sur le panier du coupon lui-même
                    'max_uses'        => 1,
                    'used_count'      => 0,
                    'starts_at'       => now(),
                    'expires_at'      => now()->addMonth(),
                    'is_active'       => true,
                    'is_seasonal'     => false,
                    'is_loyalty'      => true,
                ]);

                // Envoyer l'email transactionnel de félicitation
                try {
                    Mail::to($user)->send(new LoyaltyRewardEarned($user, $coupon));
                } catch (\Throwable $e) {
                    Log::error("Failed to send loyalty reward email to user {$user->id}: {$e->getMessage()}");
                }
            }
        }
    }

    /**
     * Récupère tous les coupons de fidélité valides et disponibles pour le client.
     */
    public function getAvailableCoupons(User $user)
    {
        return $user->coupons()
            ->where('is_loyalty', true)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->whereColumn('used_count', '<', 'max_uses')
            ->get();
    }
}

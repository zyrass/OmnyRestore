<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Support\Facades\DB;

/**
 * CouponService — Application des codes de réduction
 *
 * Valide un code coupon et calcule la remise applicable à une commande.
 * Incrémente le compteur d'utilisation lors de la confirmation.
 */
class CouponService
{
    /**
     * Valide et calcule la remise pour un code coupon.
     *
     * @param  string  $code          Code coupon saisi par le client
     * @param  int     $amountHtCents Montant HT de la commande en centimes
     * @return array{
     *   valid: bool,
     *   coupon: Coupon|null,
     *   discount_cents: int,
     *   final_ht_cents: int,
     *   message: string
     * }
     */
    public function apply(string $code, int $amountHtCents, ?\App\Models\User $user = null): array
    {
        $query = Coupon::valid()->where('code', strtoupper(trim($code)));

        if ($user) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('user_id')
                  ->orWhere('user_id', $user->id);
            });
        } else {
            $query->whereNull('user_id');
        }

        $coupon = $query->first();

        if (! $coupon) {
            return [
                'valid'          => false,
                'coupon'         => null,
                'discount_cents' => 0,
                'final_ht_cents' => $amountHtCents,
                'message'        => 'Code de réduction invalide ou expiré.',
            ];
        }

        if (! $coupon->isApplicableTo($amountHtCents)) {
            $minStr = number_format($coupon->min_order_cents / 100, 2, ',', ' ');
            return [
                'valid'          => false,
                'coupon'         => $coupon,
                'discount_cents' => 0,
                'final_ht_cents' => $amountHtCents,
                'message'        => "Ce code nécessite une commande minimum de {$minStr} € HT.",
            ];
        }

        $discountCents = $coupon->discountCents($amountHtCents);
        $finalHtCents  = max(0, $amountHtCents - $discountCents);

        return [
            'valid'          => true,
            'coupon'         => $coupon,
            'discount_cents' => $discountCents,
            'final_ht_cents' => $finalHtCents,
            'message'        => "Code appliqué : {$coupon->discount_label} de réduction.",
        ];
    }

    /**
     * Confirme l'utilisation d'un coupon (incrémente le compteur).
     * À appeler lors de la création effective de la commande.
     */
    public function confirm(Coupon $coupon): void
    {
        DB::table('coupons')
            ->where('id', $coupon->id)
            ->increment('used_count');
    }
}

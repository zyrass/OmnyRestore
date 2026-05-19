<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Coupon — Code de réduction
 *
 * @property int         $id
 * @property string      $code           Code unique (ex: "BIENVENUE10")
 * @property string|null $description    Description admin
 * @property string      $type           'percentage' | 'fixed'
 * @property int         $value          % ou centimes HT selon type
 * @property int         $min_order_cents Montant minimum commande HT
 * @property int|null    $max_uses       Limite d'utilisations (null = illimité)
 * @property int         $used_count     Compteur d'utilisations
 * @property Carbon|null $expires_at
 * @property bool        $is_active
 */
class Coupon extends Model
{
    protected $fillable = [
        'user_id', 'code', 'description', 'type', 'value',
        'min_order_cents', 'max_uses', 'used_count',
        'starts_at', 'expires_at', 'is_active', 'is_seasonal', 'is_loyalty',
    ];

    protected $casts = [
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
        'is_active'  => 'boolean',
        'is_seasonal' => 'boolean',
        'is_loyalty' => 'boolean',
        'value'      => 'integer',
        'min_order_cents' => 'integer',
        'max_uses'   => 'integer',
        'used_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    /**
     * Coupons valides : actifs, non expirés, utilisations restantes.
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function ($q) {
                $now = now();
                $todayMd = $now->format('m-d');

                // Cas 1 : Coupon Saisonier (Récurrent chaque année)
                // On vérifie si le jour/mois actuel est dans l'intervalle (ignore l'année)
                $q->where(function ($sq) use ($todayMd) {
                    $sq->where('is_seasonal', true)
                       ->whereNotNull('starts_at')
                       ->whereNotNull('expires_at')
                        ->where(function ($ssq) use ($todayMd) {
                           $driver = \DB::getDriverName();
                           $format = $driver === 'pgsql' ? "TO_CHAR(%s, 'MM-DD')" : "DATE_FORMAT(%s, '%%m-%%d')";
                           
                           $startFormat = sprintf($format, 'starts_at');
                           $endFormat = sprintf($format, 'expires_at');

                           // Gestion de l'intervalle normal (ex: 02-12 au 02-15)
                           // et de l'intervalle chevauchant l'année (ex: 12-15 au 01-01)
                           $ssq->where(function ($inner) use ($todayMd, $startFormat, $endFormat) {
                               $inner->whereRaw("$startFormat <= $endFormat")
                                     ->whereRaw("? BETWEEN $startFormat AND $endFormat", [$todayMd]);
                           })->orWhere(function ($inner) use ($todayMd, $startFormat, $endFormat) {
                               $inner->whereRaw("$startFormat > $endFormat")
                                     ->where(function ($leaf) use ($todayMd, $startFormat, $endFormat) {
                                         $leaf->whereRaw("? >= $startFormat", [$todayMd])
                                              ->orWhereRaw("? <= $endFormat", [$todayMd]);
                                     });
                           });
                       });
                });

                // Cas 2 : Coupon Standard (Une seule période fixe)
                $q->orWhere(function ($sq) use ($now) {
                    $sq->where('is_seasonal', false)
                       ->where(fn($ssq) => $ssq->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                       ->where(fn($ssq) => $ssq->whereNull('expires_at')->orWhere('expires_at', '>', $now));
                });
            })
            ->where(fn($q) => $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses'));
    }

    // ── Accessors / Helpers ──────────────────────────────────────────────────

    public function isApplicableTo(int $amountHtCents): bool
    {
        if (! $this->is_active) { return false; }
        if (! $this->is_seasonal && $this->expires_at && $this->expires_at->isPast()) { return false; }
        if ($this->max_uses && $this->used_count >= $this->max_uses) { return false; }
        if ($amountHtCents < $this->min_order_cents) { return false; }

        return true;
    }

    /**
     * Version TTC pour cohérence avec le nouveau flux financier.
     */
    public function isApplicableToTtc(int $amountTtcCents): bool
    {
        // On estime le HT pour comparer au seuil min_order_cents qui est en HT
        $estimatedHt = (int) round($amountTtcCents / 1.2);
        return $this->isApplicableTo($estimatedHt);
    }

    public function discountCents(int $amountHtCents): int
    {
        return match($this->type) {
            'percentage' => (int) round($amountHtCents * $this->value / 100),
            'fixed'      => min($this->value, $amountHtCents), 
            default      => 0,
        };
    }

    /**
     * Calcule la remise directe sur le TTC pour éviter les décalages de TVA.
     */
    public function discountTtcCents(int $amountTtcCents): int
    {
        return match($this->type) {
            'percentage' => (int) round($amountTtcCents * $this->value / 100),
            'fixed'      => (int) min(round($this->value * 1.2), $amountTtcCents),
            default      => 0,
        };
    }

    /**
     * Libellé de la réduction pour l'affichage.
     */
    public function getDiscountLabelAttribute(): string
    {
        return match($this->type) {
            'percentage' => "-{$this->value} %",
            'fixed'      => '-' . number_format($this->value / 100, 2, ',', ' ') . ' €',
            default      => '',
        };
    }

    /**
     * Utilisations restantes (null = illimité).
     */
    public function getRemainingUsesAttribute(): ?int
    {
        return $this->max_uses ? max(0, $this->max_uses - $this->used_count) : null;
    }
}

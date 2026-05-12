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
        'code', 'description', 'type', 'value',
        'min_order_cents', 'max_uses', 'used_count',
        'expires_at', 'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active'  => 'boolean',
        'value'      => 'integer',
        'min_order_cents' => 'integer',
        'max_uses'   => 'integer',
        'used_count' => 'integer',
    ];

    // ── Scopes ──────────────────────────────────────────────────────────────

    /**
     * Coupons valides : actifs, non expirés, utilisations restantes.
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn($q) => $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses'));
    }

    // ── Accessors / Helpers ──────────────────────────────────────────────────

    /**
     * Vérifie si le coupon est applicable pour un montant HT donné.
     */
    public function isApplicableTo(int $amountHtCents): bool
    {
        if (! $this->is_active) { return false; }
        if ($this->expires_at && $this->expires_at->isPast()) { return false; }
        if ($this->max_uses && $this->used_count >= $this->max_uses) { return false; }
        if ($amountHtCents < $this->min_order_cents) { return false; }

        return true;
    }

    /**
     * Calcule la remise en centimes HT pour un montant donné.
     */
    public function discountCents(int $amountHtCents): int
    {
        return match($this->type) {
            'percentage' => (int) round($amountHtCents * $this->value / 100),
            'fixed'      => min($this->value, $amountHtCents), // ne dépasse pas le montant
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

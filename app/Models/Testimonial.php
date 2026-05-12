<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Testimonial — Avis client publié sur la landing page
 *
 * Workflow :
 *   1. Client soumet un avis depuis show.blade.php (status = DELIVERED)
 *      → is_published = false, rejected_at = null (état "en attente")
 *   2. Admin modère depuis /admin/testimonials
 *      → Publier  : is_published = true
 *      → Rejeter  : rejected_at = now()
 *   3. Vitrine (welcome.blade.php) affiche scopePublished()
 *
 * @property int         $id
 * @property string|null $order_id       FK → orders (nullable)
 * @property string|null $user_id        FK → users  (nullable)
 * @property string      $author_name
 * @property string      $author_initials max 4 chars
 * @property int         $rating         1–5
 * @property string      $content
 * @property bool        $is_published
 * @property \Carbon\Carbon|null $rejected_at
 */
class Testimonial extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'author_name',
        'author_initials',
        'rating',
        'content',
        'is_published',
        'rejected_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'rating'       => 'integer',
        'rejected_at'  => 'datetime',
    ];

    // ─── Scopes ─────────────────────────────────────────────────────────────

    /** Témoignages visibles sur la vitrine. */
    public function scopePublished($query)
    {
        return $query->where('is_published', true)->whereNull('rejected_at');
    }

    /** Témoignages en attente de modération. */
    public function scopePending($query)
    {
        return $query->where('is_published', false)->whereNull('rejected_at');
    }

    /** Témoignages rejetés par l'admin. */
    public function scopeRejected($query)
    {
        return $query->whereNotNull('rejected_at');
    }

    // ─── Relations ──────────────────────────────────────────────────────────

    /** Commande à l'origine de l'avis. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Auteur du témoignage (client connecté). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    /**
     * Génère les initiales à partir d'un nom complet.
     * "Marie Dupont" → "MD" | "Jean" → "J"
     */
    public static function initialsFrom(string $name): string
    {
        $words = array_filter(explode(' ', trim($name)));
        $initials = '';
        foreach ($words as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
            if (mb_strlen($initials) >= 2) break;
        }
        return $initials ?: '?';
    }
}

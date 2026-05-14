<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Order Model — OmnyRestore
 *
 * Represents a photo restoration order placed by a client.
 * This is the central entity of the application — everything revolves around orders.
 *
 * Status State Machine:
 *   PENDING      → The client has submitted photos, waiting for admin
 *   IN_PROGRESS  → Admin has taken charge, AI restoration in progress
 *   DONE         → Admin uploaded restored photos, ZIP generated, client invited to pay
 *   CANCELLED    → Order was cancelled (by admin or client)
 *
 * Media Collections (via Spatie Media Library):
 *   'originals'   → Photos uploaded by the client (source material)
 *   'retouched'   → AI-restored photos uploaded by admin
 *   'watermarked' → Low-res preview with watermark overlay (shown before payment)
 *
 * Pricing model:
 *   - Admin reviews order and sets amount_ht + tva_rate
 *   - amount_ttc = amount_ht * (1 + tva_rate / 100)
 *   - Payment is triggered when status = DONE (client sees preview, then pays)
 *
 * @property string $id UUID primary key
 * @property string $user_id FK to users
 * @property string $reference e.g., ORD-2026-0001
 * @property string $description Client's restoration instructions
 * @property string $status PENDING | IN_PROGRESS | DONE | CANCELLED
 * @property int $photo_count Number of submitted photos
 * @property float|null $amount_ht Pre-tax amount in EUR
 * @property float $tva_rate VAT rate (default 20.00)
 * @property float|null $amount_ttc Total including VAT in EUR
 * @property string|null $payment_intent_id Stripe PaymentIntent ID
 * @property string $payment_status pending | paid | refunded | failed
 * @property \Carbon\Carbon|null $paid_at
 * @property \Carbon\Carbon|null $delivered_at
 */
class Order extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * InteractsWithMedia — Spatie Media Library trait.
     * Provides:
     *   $order->addMedia($file)->toMediaCollection('originals')
     *   $order->getMedia('retouched') → MediaCollection
     *   $order->getFirstMediaUrl('watermarked') → URL string
     *   $order->getMedia('originals')->count()
     * @see https://spatie.be/docs/laravel-medialibrary
     */
    use InteractsWithMedia;

    /**
     * Valid order statuses.
     * Used for validation in setState() and in tests.
     */
    public const STATUSES = [
        'PENDING',
        'IN_PROGRESS',
        'DONE',
        'PAID',
        'DELIVERED',
        'CANCELLED',
        'FLAGGED',
    ];

    /**
     * Valid payment statuses.
     */
    public const PAYMENT_STATUSES = [
        'pending',
        'paid',
        'refunded',
        'failed',
    ];

    /**
     * Champs assignables en masse (Mass Assignment).
     *
     * SÉCURITÉ — Champs INTENTIONNELLEMENT absents de $fillable :
     * ┌─────────────────┬───────────────────────────────────────────────────────────────┐
     * │ Champ exclu     │ Raison                                                        │
     * ├─────────────────┼───────────────────────────────────────────────────────────────┤
     * │ status          │ Doit passer par la machine d'état :                           │
     * │                 │   startProcessing() → IN_PROGRESS (depuis PENDING only)       │
     * │                 │   markAsDone()      → DONE (depuis IN_PROGRESS only)          │
     * │                 │   cancel()          → CANCELLED (depuis PENDING/IN_PROGRESS)  │
     * │                 │ Si status était dans $fillable, n'importe quel                │
     * │                 │ Order::create(['status' => 'DELIVERED']) deviendrait possible  │
     * │                 │ sans passer par les guards → fuite RGPD, accès ZIP gratuit.   │
     * ├─────────────────┼───────────────────────────────────────────────────────────────┤
     * │ payment_status  │ Doit être mis à jour uniquement via :                         │
     * │                 │   markAsPaid($paymentIntentId) → appelé par le webhook Stripe  │
     * │                 │ Si modifiable librement → un client pourrait forger un paiement│
     * │                 │ (ex: Order::update(['payment_status' => 'paid'])).             │
     * └─────────────────┴───────────────────────────────────────────────────────────────┘
     *
     * Ces champs sont modifiés via forceFill() UNIQUEMENT dans les méthodes dédiées
     * de ce même modèle, ce qui maintient le contrôle total de leur cycle de vie.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'reference',
        'description',
        'photo_count',
        'damage_level',
        'instructions',
        'base_price_cents',
        'total_price_cents',
        'amount_ht',
        'tva_rate',
        'amount_ttc',
        // ⚠️ 'status'         — EXCLU : utiliser startProcessing(), markAsDone(), cancel()
        // ⚠️ 'payment_status' — EXCLU : utiliser markAsPaid() via webhook Stripe uniquement
        'payment_intent_id',
        'admin_notes',
        'paid_at',
        'delivered_at',
        'zip_path',
        'zip_expires_at',
        'coupon_code',
        'discount_cents',
        'preview_unlocked_at',   // Email-gate : renseigné par UnlockPreviewController au clic
        'billing_name',
        'billing_email',
        'client_ip',
    ];

    /**
     * Type casting.
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_ht'         => 'decimal:2',
            'tva_rate'          => 'decimal:2',
            'amount_ttc'        => 'decimal:2',
            'base_price_cents'  => 'integer',
            'total_price_cents' => 'integer',
            'paid_at'           => 'datetime',
            'delivered_at'      => 'datetime',
            'zip_expires_at'       => 'datetime',
            'photo_count'          => 'integer',
            'preview_unlocked_at'  => 'datetime',
        ];
    }

    // =========================================================================
    // MODEL BOOT — AUTOMATIC BEHAVIORS
    // =========================================================================

    /**
     * Boot the model.
     *
     * Registers model event listeners:
     *   - 'creating': Auto-generate the human-readable reference (ORD-2026-XXXX)
     *                 before the record is first inserted.
     *
     * @see https://laravel.com/docs/eloquent#events
     */
    protected static function boot(): void
    {
        parent::boot();

        /**
         * Before creating a new order, generate a unique sequential reference.
         * Format: ORD-{YEAR}-{4-digit-padded-count}
         * Example: ORD-2026-0001, ORD-2026-0042, ORD-2026-1337
         *
         * Note: This is NOT using database auto-increment to maintain the format
         * across potential database resets in different environments.
         */
        static::creating(function (Order $order) {
            $year    = now()->year;
            $count   = static::whereYear('created_at', $year)->count() + 1;
            $order->reference = sprintf('ORD-%d-%04d', $year, $count);
        });
    }

    // =========================================================================
    // SPATIE MEDIA LIBRARY — COLLECTION REGISTRATION
    // =========================================================================

    /**
     * Register media collections for this model.
     *
     * Spatie requires declaring collections here so it knows their rules.
     * Each collection can have its own disk, conversions, and constraints.
     *
     * @see https://spatie.be/docs/laravel-medialibrary/working-with-media-collections
     */
    public function registerMediaCollections(): void
    {
        // Le disk est configuré via .env → MEDIA_DISK (défaut: 'public' en local, 's3' en prod)
        $disk = config('media-library.disk_name', config('filesystems.default', 'public'));

        // ─── Originals ────────────────────────────────────────────────────
        // Photos uploadées par le client — visibles dans l'espace admin uniquement.
        $this->addMediaCollection('originals')
             ->useDisk($disk)
             ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/tiff', 'image/webp']);

        // ─── Retouched ────────────────────────────────────────────────────
        // Photos restaurées par l'admin (haute résolution).
        // ⚠️ DISK 'local' OBLIGATOIRE : ces fichiers ne doivent JAMAIS être
        // accessibles via /storage/ (URL publique directe).
        // Accès uniquement via SecurePhotoController (auth + checks).
        // En production (S3) : utiliser un bucket PRIVÉ et des presigned URLs.
        $privateDisk = app()->environment('production') ? 's3' : 'local';
        $this->addMediaCollection('retouched')
             ->useDisk($privateDisk);

        // ─── Watermarked Preview ──────────────────────────────────────────
        // Aperçus basse résolution filigranés, montrés avant paiement.
        $this->addMediaCollection('watermarked')
             ->useDisk($disk);
    }

    // =========================================================================
    // STATE MACHINE METHODS
    // =========================================================================

    /**
     * Transition the order to IN_PROGRESS status.
     *
     * Called when admin clicks "Take charge" in the back office.
     * Only valid from PENDING status.
     *
     * @throws \InvalidArgumentException If the current status doesn't allow this transition
     */
    public function startProcessing(): void
    {
        $this->guardTransition('IN_PROGRESS', ['PENDING']);
        $this->status = 'IN_PROGRESS';
        $this->save();
    }

    /**
     * Transition the order to DONE status.
     *
     * Called when admin clicks "Mark as done" after uploading restored photos.
     * This triggers:
     *   1. ZIP generation job (GenerateOrderZipJob)
     *   2. Watermarked preview generation
     *   3. Email notification to client (payment link)
     *
     * Only valid from IN_PROGRESS status.
     */
    public function markAsDone(): void
    {
        $this->guardTransition('DONE', ['IN_PROGRESS']);
        $this->status       = 'DONE';
        $this->delivered_at = now();
        $this->save();
    }

    /**
     * Transition the order to DELIVERED status.
     *
     * Called when the admin clicks the button to send the ZIP + Invoice email.
     * Only valid from PAID or already DELIVERED status.
     */
    public function markAsDelivered(): void
    {
        $this->guardTransition('DELIVERED', ['PAID', 'DELIVERED']);
        $this->status = 'DELIVERED';
        if (! $this->delivered_at) {
            $this->delivered_at = now();
        }
        
        // Expiration de l'archive ZIP : 90 jours
        if (! $this->zip_expires_at) {
            $this->zip_expires_at = now()->addDays(90);
        }
        
        $this->save();
    }

    /**
     * Transition the order to CANCELLED status.
     *
     * Valid from PENDING or IN_PROGRESS.
     * Admin can cancel an unrestorable order (damaged beyond recovery).
     *
     * @param string $reason Optional reason for cancellation (stored in admin_notes)
     */
    public function cancel(string $reason = ''): void
    {
        $this->guardTransition('CANCELLED', ['PENDING', 'IN_PROGRESS']);
        $this->status = 'CANCELLED';
        if ($reason) {
            $this->admin_notes = $reason;
        }
        $this->save();
    }

    /**
     * Mark the order as paid.
     *
     * Called by the Stripe webhook handler (payment_intent.succeeded event).
     * This is the authoritative payment confirmation — do NOT call this from
     * the frontend (it would be trivially spoofable).
     *
     * @param string $paymentIntentId The Stripe PaymentIntent ID for audit
     */
    public function markAsPaid(string $paymentIntentId): void
    {
        $this->guardTransition('PAID', ['DONE']);

        $this->status             = 'PAID';
        $this->payment_intent_id  = $paymentIntentId;
        $this->payment_status     = 'paid';
        $this->paid_at            = now();
        $this->save();
    }

    /**
     * Guard against invalid state transitions.
     *
     * Throws an exception if the current status is not in the allowed list.
     * This is the core of the state machine enforcement.
     *
     * @param string   $to      The target status
     * @param string[] $from    Array of allowed source statuses
     * @throws \InvalidArgumentException
     */
    protected function guardTransition(string $to, array $from): void
    {
        if (! in_array($this->status, $from, true)) {
            throw new \InvalidArgumentException(
                "Cannot transition order {$this->reference} from '{$this->status}' to '{$to}'. " .
                "Allowed source statuses: " . implode(', ', $from)
            );
        }
    }

    // =========================================================================
    // COMPUTED PROPERTIES / HELPERS
    // =========================================================================

    /**
     * Calculate the TTC (tax-included) amount from HT amount and TVA rate.
     * Called when admin sets the price to auto-compute the total.
     */
    public function computeAmountTtc(): float
    {
        return round((float) $this->amount_ht * (1 + (float) $this->tva_rate / 100), 2);
    }

    /**
     * Convert the TTC amount to cents (for Stripe).
     *
     * Stripe requires amounts in the SMALLEST currency unit (cents for EUR).
     * Example: €49.90 → 4990 cents
     */
    public function getAmountInCents(): int
    {
        return (int) round((float) $this->amount_ttc * 100);
    }

    /**
     * Check if this order can be downloaded (paid + delivery exists).
     */
    public function isDownloadable(): bool
    {
        return $this->payment_status === 'paid' && $this->delivery !== null;
    }

    /**
     * Check if this order is awaiting payment.
     */
    public function awaitingPayment(): bool
    {
        return $this->status === 'DONE' && $this->payment_status === 'pending';
    }

    // =========================================================================
    // ELOQUENT RELATIONSHIPS
    // =========================================================================

    /**
     * The client who placed this order.
     *
     * Usage: $order->user → User model
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The ZIP delivery associated with this order.
     *
     * One-to-one: one order has at most one delivery record.
     * Null until GenerateOrderZipJob completes.
     *
     * Usage: $order->delivery → OrderDelivery|null
     *
     * @return HasOne<OrderDelivery, $this>
     */
    public function delivery(): HasOne
    {
        return $this->hasOne(OrderDelivery::class, 'order_id');
    }

    /**
     * All audit log entries related to this order.
     *
     * Usage: $order->auditLogs → Collection of AuditLog models
     *
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'subject_id')
                    ->where('subject_type', self::class);
    }

    /**
     * Avis client associé à cette commande (1 seul avis par commande).
     *
     * @return HasOne<\App\Models\Testimonial, $this>
     */
    public function testimonial(): HasOne
    {
        return $this->hasOne(\App\Models\Testimonial::class, 'order_id');
    }

    // =========================================================================
    // ELOQUENT SCOPES
    // =========================================================================

    /**
     * Scope: Only orders with PENDING status.
     * Usage: Order::pending()->get()
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopePending(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('status', 'PENDING');
    }

    /**
     * Scope: Only orders currently being processed.
     * Usage: Order::inProgress()->count()
     */
    public function scopeInProgress(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('status', 'IN_PROGRESS');
    }

    /**
     * Scope: Only orders ready for client payment.
     * Usage: Order::awaitingPayment()->get()
     */
    public function scopeAwaitingPayment(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('status', 'DONE')->where('payment_status', 'pending');
    }

    /**
     * Scope: Only paid orders (delivery unlocked).
     * Usage: Order::paid()->latest()->get()
     */
    public function scopePaid(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('payment_status', 'paid');
    }

    /**
     * Retourne le décompte des photos par niveau de dommage IA.
     * Utile pour la transparence tarifaire en admin et client.
     *
     * @return array<string, int>
     */
    /**
     * Retourne le décompte des photos par niveau de dommage IA.
     * Utile pour la transparence tarifaire en admin et client.
     *
     * @return array<string, int>
     */
    public function getDamageBreakdown(): array
    {
        $breakdown = ['light' => 0, 'medium' => 0, 'heavy' => 0];
        
        // Priorité aux photos retouchées (actives uniquement)
        $media = $this->getMedia('retouched');
        if ($media->isNotEmpty()) {
            $media = $media->filter(fn($m) => ! $m->getCustomProperty('is_rejected', false));
        } else {
            // Sinon on se base sur les originaux
            $media = $this->getMedia('originals');
        }

        foreach ($media as $m) {
            $lv = $m->getCustomProperty('ai_level', $this->damage_level ?? 'light');
            if (isset($breakdown[$lv])) {
                $breakdown[$lv]++;
            } else {
                $breakdown['light']++; // fallback
            }
        }

        return array_filter($breakdown); // On ne garde que les niveaux présents
    }

    /**
     * Calcule le montant TTC total en centimes d'après les photos actives.
     * C'est la source de vérité pour Stripe, la facture et les emails.
     */
    /**
     * Calcule le montant TTC total en centimes d'après les photos actives ou le prix forcé.
     * C'est la source de vérité absolue pour Stripe, la facture et les emails.
     */
    public function getAmountTtcCents(): int
    {
        // 1. Priorité absolue : le prix total fixé (HT ou TTC selon contexte, mais on l'unifie en TTC Net)
        // Note : Si l'admin a saisi un prix, il est stocké ici.
        if ($this->total_price_cents !== null) {
            return (int) max(0, $this->total_price_cents - ($this->discount_cents ?? 0));
        }

        // 2. Fallback : calcul dynamique par média (TTC exact par niveau)
        $_pttc = \App\Services\PhotoDamageAnalyzer::PRICES_TTC;
        
        $media = $this->getMedia('retouched');
        if ($media->isNotEmpty()) {
            $media = $media->filter(fn($m) => ! $m->getCustomProperty('is_rejected', false));
        } else {
            $media = $this->getMedia('originals');
        }

        $baseTtcC = $media->sum(function ($m) use ($_pttc) {
            $lv = $m->getCustomProperty('ai_level', $this->damage_level ?? 'light');
            return $_pttc[$lv] ?? $_pttc['light'];
        });

        // 3. Dernier recours : base_price_cents (converti en TTC si nécessaire)
        if ($baseTtcC === 0 && $this->base_price_cents > 0) {
            $baseTtcC = (int) round($this->base_price_cents * 1.2);
        }

        return (int) max(0, $baseTtcC - ($this->discount_cents ?? 0));
    }

    /**
     * Calcule le montant HT à partir du TTC (Calcul inverse).
     * Utilisé pour la transparence sur la facture PDF.
     */
    public function getAmountHtCents(): int
    {
        $ttc = $this->getAmountTtcCents();
        return (int) round($ttc / 1.2);
    }

    /**
     * Retourne le nombre de photos actives (non rejetées).
     */
    public function getActivePhotosCount(): int
    {
        $retouched = $this->getMedia('retouched');
        if ($retouched->isEmpty()) {
            return $this->getMedia('originals')->count() ?: $this->photo_count;
        }

        return $retouched->filter(fn($m) => ! $m->getCustomProperty('is_rejected', false))->count();
    }
}

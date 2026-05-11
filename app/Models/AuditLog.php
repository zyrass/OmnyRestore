<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AuditLog Model — OmnyRestore
 *
 * Immutable record of all significant actions in the platform.
 * Two purposes:
 *   1. GDPR compliance: Required traceability (GDPR Art. 30)
 *   2. Security monitoring (NIS2 Art. 21 — Logging measures)
 *
 * Design:
 *   - NO updated_at: write-once, read-only records
 *   - user_id has no DB foreign key (GDPR: logs must survive user deletion)
 *   - Polymorphic subject: any model can be the target of an action
 *   - payload JSON: flexible context storage per action type
 *
 * Tracked actions (action column):
 *   ORDER_CREATED, ORDER_STATUS_CHANGED, PAYMENT_INITIATED,
 *   PAYMENT_SUCCEEDED, PAYMENT_FAILED, DOWNLOAD_INITIATED,
 *   LOGIN, LOGIN_FAILED, GDPR_EXPORT, GDPR_ERASURE
 *
 * @see App\Services\AuditService — use this service to create logs, never Model::create() directly
 */
class AuditLog extends Model
{
    use HasUuids;

    /**
     * Disable updated_at — audit logs are immutable.
     * Laravel will not try to set this column.
     */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'payload',
        'ip_address',
        'user_agent',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload'    => 'array',  // JSON auto-decoded to PHP array
            'created_at' => 'datetime',
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * The user who performed the action.
     * May be null for system-initiated actions (scheduled jobs, Stripe webhooks).
     *
     * Note: We do NOT use a foreign key constraint on user_id.
     * This allows users to be deleted (GDPR erasure) while preserving audit logs.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Polymorphic subject: the entity the action was performed on.
     * Can be: Order, User, or any future model.
     * Usage: $log->subject → Order|User|null
     */
    public function subject()
    {
        return $this->morphTo('subject');
    }
}

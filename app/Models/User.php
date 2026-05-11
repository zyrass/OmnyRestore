<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

/**
 * User Model — OmnyRestore
 *
 * Represents a registered user on the platform.
 * A user can be either a 'client' (places orders) or 'admin' (manages orders).
 *
 * Key traits used:
 *   - HasUuids: Automatically generates UUID v7 for the 'id' field on creation
 *   - SoftDeletes: Adds deleted_at field; records are "hidden" not permanently removed
 *   - Billable (Cashier): Adds Stripe customer management, payment methods, etc.
 *   - Notifiable: Allows sending notifications (email, SMS) via Laravel's notification system
 *
 * GDPR Design:
 *   When a user requests erasure (Right to Erasure — GDPR Art. 17):
 *   1. $user->delete() → sets deleted_at (soft delete)
 *   2. A scheduled job anonymizes: name → "Deleted User", email → "deleted_{uuid}@erased.local"
 *   3. stripe_id is cleared (Stripe customer is also deleted via API)
 *   4. rgpd_consent_at is preserved (proof of consent must be kept)
 *   5. All S3 media files are scheduled for deletion
 *   → Order records are KEPT (5-year accounting legal requirement)
 *
 * @property string $id UUID primary key
 * @property string $name Full name
 * @property string $email Email address (unique)
 * @property string $role 'client' | 'admin'
 * @property string|null $stripe_id Stripe Customer ID
 * @property \Carbon\Carbon|null $rgpd_consent_at GDPR consent timestamp
 * @property bool $marketing_consent Marketing email opt-in
 * @property \Carbon\Carbon|null $deleted_at Soft delete timestamp
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    /**
     * HasUuids — Automatically assigns UUID v7 as primary key on model creation.
     * UUID v7 is time-ordered, which maintains insert performance on indexed columns.
     * @see https://laravel.com/docs/eloquent#uuid-and-ulid-keys
     */
    use HasUuids;

    /**
     * SoftDeletes — Overrides delete() to set deleted_at instead of removing the row.
     * All queries automatically exclude soft-deleted records (WHERE deleted_at IS NULL).
     * Use User::withTrashed() to include them, User::onlyTrashed() to see only deleted.
     * @see https://laravel.com/docs/eloquent#soft-deleting
     */
    use SoftDeletes;

    /**
     * Billable — Laravel Cashier trait for Stripe integration.
     * Provides methods like:
     *   $user->createOrGetStripeCustomer()
     *   $user->charge(amount, paymentMethod)
     *   $user->invoices()
     *   $user->subscribe('price_id')
     * @see https://laravel.com/docs/billing
     */
    use Billable;

    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * Only these fields can be set via $user->fill([...]) or User::create([...]).
     * This prevents mass-assignment attacks (OWASP A04).
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'rgpd_consent_at',
        'marketing_consent',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * These fields are NEVER included in JSON responses ($user->toArray() / toJson()).
     * Critical: password and remember_token must never be exposed via API.
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * Casting ensures correct PHP types when reading from the database.
     * Without casting, everything comes back as a string from the DB driver.
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',   // Carbon instance
            'rgpd_consent_at'   => 'datetime',   // Carbon instance
            'marketing_consent' => 'boolean',    // true/false (not 1/0)
            'password'          => 'hashed',     // Auto-hashes on set (Laravel 10+)
            'deleted_at'        => 'datetime',   // Carbon instance (soft delete)
        ];
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Check if this user is an administrator.
     *
     * Used in: Middleware, Policies, Blade @can directives.
     * Example: @if(auth()->user()->isAdmin())
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if this user is a client.
     *
     * Example: middleware('role:client')
     */
    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    /**
     * Check if the user has verified their email address.
     *
     * Used as a prerequisite for placing orders (enforced via 'verified' middleware).
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    // =========================================================================
    // ELOQUENT RELATIONSHIPS
    // =========================================================================

    /**
     * All orders placed by this client.
     *
     * Usage:
     *   $user->orders → Collection of Order models
     *   $user->orders()->where('status', 'PENDING')->get()
     *   $user->orders()->count()
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    /**
     * All audit log entries associated with this user.
     *
     * Includes all actions performed BY this user.
     * Note: audit_logs.user_id does not use a DB foreign key (see migration comments).
     *
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }
}

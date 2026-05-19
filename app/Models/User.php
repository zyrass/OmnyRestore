<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\MustVerifyEmail;
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
 * GDPR Design — DeleteUserAction:
 *   When a user requests erasure (Right to Erasure — GDPR Art. 17):
 *   1. Password verified → all media (originals + retouched) deleted via Spatie
 *   2. Support tickets deleted (no legal obligation to retain)
 *   3. PII anonymized immediately: name → "Utilisateur supprimé", email → "deleted_{hash}@data.deleted"
 *   4. stripe_id cleared, marketing_consent = false, password invalidated
 *   5. anonymized_at = now() (audit trail RGPD)
 *   6. soft-delete: deleted_at = now()
 *   → rgpd_consent_at is KEPT (proof of initial consent — RGPD Art. 7.1)
 *   → Orders KEPT with anonymized user_id (invoices: 10y legal — L.123-22 C.com)
 *
 * @property string $id UUID primary key
 * @property string $name Full name
 * @property string $email Email address (unique)
 * @property string $role 'client' | 'admin'
 * @property string|null $stripe_id Stripe Customer ID
 * @property \Carbon\Carbon|null $rgpd_consent_at GDPR consent timestamp
 * @property bool $marketing_consent Marketing email opt-in
 * @property \Carbon\Carbon|null $deleted_at Soft delete timestamp
 * @property \Carbon\Carbon|null $anonymized_at RGPD anonymization audit timestamp
 */
class User extends Authenticatable implements MustVerifyEmail
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
        'contact_email',
        'password',
        'role',
        'rgpd_consent_at',
        'marketing_consent',
        'last_login_at',
        'suspended_at',
        'hire_date',
        'contract_type',
        'net_salary',
        'hr_notes',
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
            'contact_email'     => 'string',     // Cast string
            'rgpd_consent_at'   => 'datetime',   // Carbon instance
            'anonymized_at'     => 'datetime',   // Carbon instance — audit RGPD
            'marketing_consent' => 'boolean',    // true/false (not 1/0)
            'password'          => 'hashed',     // Auto-hashes on set (Laravel 10+)
            'deleted_at'        => 'datetime',   // Carbon instance (soft delete)
            'last_login_at'     => 'datetime',   // Carbon instance
            'suspended_at'      => 'datetime',   // Carbon instance
            'hire_date'         => 'date',       // Date instance
            'net_salary'        => 'decimal:2',  // Decimal for currency
        ];
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Check if this user's account is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Check if this user is a super-administrator (Legacy isAdmin alias).
     *
     * Used in: Middleware, Policies, Blade @can directives.
     */
    public function isAdmin(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Check if this user is the super-administrator (Owner).
     * Has full access including finances and RBAC settings.
     */
    public function isSuperAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super-admin']);
    }

    /**
     * Check if this user is part of the staff (Operator, Marketing, HR, or Super-Admin).
     * Has access to the main dashboard, orders, and tickets.
     */
    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'super-admin', 'operator', 'marketing', 'rh']);
    }

    /**
     * Check if this user is an HR Manager.
     */
    public function isRH(): bool
    {
        return $this->role === 'rh';
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

    /**
     * Route notifications for the mail channel.
     * If a secure contact email is defined, route all notifications there.
     */
    public function routeNotificationForMail($notification = null): string
    {
        return $this->contact_email ?? $this->email;
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

    /**
     * All support tickets opened by this client.
     * Loaded eagerly in DeleteUserAction to delete tickets before anonymization.
     *
     * @return HasMany<SupportTicket, $this>
     */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'user_id');
    }

    /**
     * Tous les témoignages laissés par ce client.
     *
     * @return HasMany<Testimonial, $this>
     */
    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class, 'user_id');
    }

    /**
     * Historique des salaires de l'utilisateur.
     *
     * @return HasMany<SalaryHistory, $this>
     */
    public function salaryHistories(): HasMany
    {
        return $this->hasMany(SalaryHistory::class, 'user_id');
    }

    /**
     * Tous les coupons personnels de l'utilisateur.
     *
     * @return HasMany<Coupon, $this>
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class, 'user_id');
    }

    /**
     * Nombre de commandes payées/livrées d'au moins 10€ TTC éligibles à la fidélité.
     */
    public function eligibleOrdersCount(): int
    {
        return $this->orders()
            ->whereIn('status', ['PAID', 'DELIVERED'])
            ->where('total_price_cents', '>=', 1000)
            ->count();
    }

    /**
     * Progression dans le cycle de fidélité actuel (0, 1 ou 2 sur 3).
     */
    public function loyaltyProgress(): int
    {
        return $this->eligibleOrdersCount() % 3;
    }
}

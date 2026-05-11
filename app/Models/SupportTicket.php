<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasUuids;

    protected $table = 'support_tickets';

    protected $fillable = [
        'user_id', 'order_id', 'reference', 'subject',
        'status', 'priority', 'closed_at',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    // ── Auto-numérotation ──────────────────────────────────────────────────
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $ticket) {
            if (empty($ticket->reference)) {
                $year  = now()->year;
                $count = static::whereYear('created_at', $year)->count() + 1;
                $ticket->reference = 'TKT-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    // ── Relations ──────────────────────────────────────────────────────────
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function messages(): HasMany { return $this->hasMany(SupportTicketMessage::class, 'ticket_id'); }

    // ── Scopes ────────────────────────────────────────────────────────────
    public function scopeOpen($q)    { return $q->where('status', 'open'); }
    public function scopePending($q) { return $q->where('status', 'pending'); }
    public function scopeClosed($q)  { return $q->where('status', 'closed'); }

    // ── Helpers ───────────────────────────────────────────────────────────
    public function isOpen(): bool   { return $this->status === 'open'; }
    public function isClosed(): bool { return $this->status === 'closed'; }

    public function lastMessage(): ?SupportTicketMessage
    {
        return $this->messages()->latest()->first();
    }

    public function unreadCount(bool $forAdmin = false): int
    {
        return $this->messages()
            ->where('is_admin', ! $forAdmin) // messages de l'autre partie
            ->where('is_read', false)
            ->count();
    }
}

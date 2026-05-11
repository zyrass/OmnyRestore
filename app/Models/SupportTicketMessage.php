<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketMessage extends Model
{
    use HasUuids;

    protected $table = 'support_ticket_messages';

    protected $fillable = ['ticket_id', 'user_id', 'body', 'is_admin', 'is_read'];

    protected $casts = ['is_admin' => 'boolean', 'is_read' => 'boolean'];

    public function ticket(): BelongsTo { return $this->belongsTo(SupportTicket::class, 'ticket_id'); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
}

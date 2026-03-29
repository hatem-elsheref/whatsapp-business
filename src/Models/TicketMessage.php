<?php

namespace WhatsApp\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    protected $table = 'wa_ticket_messages';

    protected $fillable = [
        'ticket_id',
        'agent_id',
        'message_id',
        'type',
        'content',
        'is_internal',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function isNote(): bool
    {
        return $this->type === 'note';
    }

    public function isReply(): bool
    {
        return $this->type === 'reply';
    }

    public function isSystemMessage(): bool
    {
        return $this->type === 'system';
    }

    public function scopeVisible($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }
}

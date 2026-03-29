<?php

namespace WhatsApp\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $table = 'wa_tickets';

    protected $fillable = [
        'customer_id',
        'conversation_id',
        'ticket_number',
        'subject',
        'description',
        'priority',
        'status',
        'assigned_agent_id',
        'created_by_agent_id',
        'closed_by_agent_id',
        'resolved_by_agent_id',
        'resolution_notes',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'response_count',
        'message_count',
        'tags',
        'metadata',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'response_count' => 'integer',
        'message_count' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'created_by_agent_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'resolved_by_agent_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'closed_by_agent_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'ticket_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isUrgent(): bool
    {
        return $this->priority === 'urgent';
    }

    public function markAsResolved(?int $agentId = null, ?string $notes = null): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by_agent_id' => $agentId,
            'resolution_notes' => $notes,
        ]);
    }

    public function markAsClosed(?int $agentId = null): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by_agent_id' => $agentId,
        ]);
    }

    public function recordFirstResponse(): void
    {
        if (!$this->first_response_at) {
            $this->update(['first_response_at' => now()]);
        }
        $this->increment('response_count');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'pending']);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_agent_id');
    }

    public static function generateTicketNumber(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 4));
        return "TKT-{$date}-{$random}";
    }
}

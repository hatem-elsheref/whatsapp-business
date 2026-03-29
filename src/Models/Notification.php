<?php

namespace WhatsApp\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'wa_notifications';

    protected $fillable = [
        'customer_id',
        'agent_id',
        'conversation_id',
        'ticket_id',
        'type',
        'title',
        'body',
        'data',
        'action_url',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function isRead(): bool
    {
        return $this->is_read;
    }

    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeByAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public static function createForAgent(
        int $agentId,
        string $type,
        string $title,
        ?string $body = null,
        ?array $data = null,
        ?string $actionUrl = null
    ): self {
        $agent = Agent::findOrFail($agentId);
        
        return self::create([
            'customer_id' => $agent->customer_id,
            'agent_id' => $agentId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'action_url' => $actionUrl,
        ]);
    }
}

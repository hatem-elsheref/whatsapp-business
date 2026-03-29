<?php

namespace WhatsApp\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    protected $table = 'wa_analytics_events';

    protected $fillable = [
        'customer_id',
        'phone_number_id',
        'agent_id',
        'event_type',
        'event_data',
        'wa_id',
        'conversation_id',
        'message_id',
        'occurred_at',
    ];

    protected $casts = [
        'event_data' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class, 'phone_number_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public static function record(
        int $customerId,
        string $eventType,
        ?int $phoneNumberId = null,
        ?int $agentId = null,
        ?string $waId = null,
        ?string $conversationId = null,
        ?string $messageId = null,
        ?array $eventData = null
    ): self {
        return self::create([
            'customer_id' => $customerId,
            'phone_number_id' => $phoneNumberId,
            'agent_id' => $agentId,
            'event_type' => $eventType,
            'event_data' => $eventData,
            'wa_id' => $waId,
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'occurred_at' => now(),
        ]);
    }

    public static function messageReceived(int $customerId, int $phoneNumberId, string $waId, string $conversationId, string $messageId): self
    {
        return self::record($customerId, 'message_received', $phoneNumberId, null, $waId, $conversationId, $messageId);
    }

    public static function messageSent(int $customerId, int $phoneNumberId, string $conversationId, string $messageId, ?int $agentId = null): self
    {
        return self::record($customerId, 'message_sent', $phoneNumberId, $agentId, null, $conversationId, $messageId);
    }

    public static function conversationStarted(int $customerId, int $phoneNumberId, string $conversationId): self
    {
        return self::record($customerId, 'conversation_started', $phoneNumberId, null, null, $conversationId);
    }

    public static function ticketCreated(int $customerId, string $ticketNumber): self
    {
        return self::record($customerId, 'ticket_created', null, null, null, null, null, ['ticket_number' => $ticketNumber]);
    }

    public static function scopeByType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public static function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('occurred_at', [$startDate, $endDate]);
    }
}

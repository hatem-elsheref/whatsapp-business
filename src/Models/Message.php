<?php

namespace WhatsApp\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $table = 'wa_messages';

    protected $fillable = [
        'customer_id',
        'phone_number_id',
        'conversation_id',
        'meta_message_id',
        'message_id',
        'direction',
        'type',
        'body',
        'media_url',
        'media_mime_type',
        'media_caption',
        'media_sha256',
        'media_size',
        'sticker_id',
        'location',
        'contact',
        'status',
        'error_message',
        'retry_count',
        'is_template_reply',
        'template_id',
        'quick_reply_id',
        'flow_step_id',
        'sent_by_agent_id',
        'buttons',
        'metadata',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
    ];

    protected $casts = [
        'location' => 'array',
        'contact' => 'array',
        'buttons' => 'array',
        'metadata' => 'array',
        'is_template_reply' => 'boolean',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class, 'phone_number_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function sentByAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'sent_by_agent_id');
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    public function isDelivered(): bool
    {
        return in_array($this->status, ['delivered', 'read']);
    }

    public function isRead(): bool
    {
        return $this->status === 'read';
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'failed_temporary']);
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsRead(): void
    {
        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }

    public function markAsFailed(string $error = null): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'failed_at' => now(),
        ]);
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    public function scopeByConversation($query, $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }
}

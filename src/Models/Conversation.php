<?php

namespace WhatsApp\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Conversation extends Model
{
    protected $table = 'wa_conversations';

    protected $fillable = [
        'customer_id',
        'phone_number_id',
        'wa_id',
        'customer_name',
        'customer_profile_photo_url',
        'customer_email',
        'customer_phone',
        'last_message_id',
        'last_message_at',
        'last_message_preview',
        'last_message_direction',
        'status',
        'window_expires_at',
        'unread_count',
        'assigned_agent_id',
        'metadata',
        'context',
        'source',
    ];

    protected $casts = [
        'metadata' => 'array',
        'context' => 'array',
        'last_message_at' => 'datetime',
        'window_expires_at' => 'datetime',
        'unread_count' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class, 'phone_number_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'conversation_id');
    }

    public function flowUserData(): HasMany
    {
        return $this->hasMany(FlowUserData::class, 'conversation_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'conversation_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isWindowExpired(): bool
    {
        if (!$this->window_expires_at) {
            return false;
        }
        return $this->window_expires_at->isPast();
    }

    public function getWindowTimeRemaining(): ?Carbon
    {
        if (!$this->window_expires_at) {
            return null;
        }
        
        $now = Carbon::now();
        if ($this->window_expires_at->isPast()) {
            return null;
        }
        
        return $now->diff($this->window_expires_at);
    }

    public function canSendFreeformMessage(): bool
    {
        return !$this->isWindowExpired();
    }

    public function incrementUnread(): void
    {
        $this->increment('unread_count');
    }

    public function markAsRead(): void
    {
        $this->update(['unread_count' => 0]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_agent_id');
    }

    public function scopeByPhoneNumber($query, $phoneNumberId)
    {
        return $query->where('phone_number_id', $phoneNumberId);
    }
}

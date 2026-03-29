<?php

namespace WhatsApp\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhoneNumber extends Model
{
    protected $table = 'wa_phone_numbers';

    protected $fillable = [
        'customer_id',
        'phone_number_id',
        'raw_number',
        'display_number',
        'name',
        'waba_id',
        'waba_name',
        'quality_score',
        'status',
        'webhook_verified',
        'webhook_url',
        'webhook_verify_token',
        'capabilities',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'webhook_verified' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'phone_number_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class, 'phone_number_id');
    }

    public function flows(): HasMany
    {
        return $this->hasMany(Flow::class, 'phone_number_id');
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected' && $this->is_active;
    }

    public function canReceiveMessages(): bool
    {
        return $this->isConnected() && $this->webhook_verified;
    }
}

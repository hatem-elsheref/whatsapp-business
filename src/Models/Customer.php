<?php

namespace WhatsApp\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Customer extends Model
{
    protected $table = 'wa_customers';

    protected $fillable = [
        'meta_user_id',
        'business_name',
        'business_email',
        'access_token',
        'token_expires_at',
        'refresh_token',
        'meta_business_id',
        'meta_business_name',
        'settings',
        'permissions',
        'is_active',
        'is_verified',
    ];

    protected $casts = [
        'settings' => 'array',
        'permissions' => 'array',
        'token_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccessTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setRefreshTokenAttribute($value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getRefreshTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(PhoneNumber::class, 'customer_id');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'customer_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'customer_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class, 'customer_id');
    }

    public function flows(): HasMany
    {
        return $this->hasMany(Flow::class, 'customer_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'customer_id');
    }

    public function quickReplies(): HasMany
    {
        return $this->hasMany(QuickReply::class, 'customer_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'customer_id');
    }

    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return true;
        }
        return $this->token_expires_at->isPast();
    }
}

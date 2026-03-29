<?php

namespace WhatsApp\Business\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'wa_agents';

    protected $fillable = [
        'customer_id',
        'name',
        'email',
        'password',
        'avatar_url',
        'role',
        'is_active',
        'pusher_channel',
        'last_active_at',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_active_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_agent_id');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_agent_id');
    }

    public function createdTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'created_by_agent_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'agent_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sent_by_agent_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function updateActivity(): void
    {
        $this->update(['last_active_at' => now()]);
    }
}

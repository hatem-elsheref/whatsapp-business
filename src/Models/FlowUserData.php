<?php

namespace WhatsApp\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowUserData extends Model
{
    protected $table = 'wa_flow_user_data';

    protected $fillable = [
        'customer_id',
        'conversation_id',
        'flow_id',
        'variables',
        'current_step',
        'current_step_id',
        'status',
        'started_at',
        'completed_at',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'variables' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class, 'flow_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function setVariable(string $key, $value): void
    {
        $variables = $this->variables ?? [];
        $variables[$key] = $value;
        $this->variables = $variables;
    }

    public function getVariable(string $key, $default = null)
    {
        $variables = $this->variables ?? [];
        return $variables[$key] ?? $default;
    }

    public function getAllVariables(): array
    {
        return $this->variables ?? [];
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function abandon(): void
    {
        $this->update([
            'status' => 'abandoned',
            'completed_at' => now(),
        ]);
    }
}

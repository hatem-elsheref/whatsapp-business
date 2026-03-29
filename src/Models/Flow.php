<?php

namespace WhatsApp\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flow extends Model
{
    protected $table = 'wa_flows';

    protected $fillable = [
        'customer_id',
        'phone_number_id',
        'name',
        'description',
        'trigger_type',
        'trigger_value',
        'is_active',
        'allow_user_interruption',
        'max_steps',
        'timeout_minutes',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'allow_user_interruption' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class, 'phone_number_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(FlowStep::class, 'flow_id')->orderBy('step_order');
    }

    public function userData(): HasMany
    {
        return $this->hasMany(FlowUserData::class, 'flow_id');
    }

    public function getStartStep(): ?FlowStep
    {
        return $this->steps()->orderBy('step_order')->first();
    }

    public function isKeywordTriggered(): bool
    {
        return $this->trigger_type === 'keyword';
    }

    public function matchesKeyword(string $input): bool
    {
        if (!$this->isKeywordTriggered()) {
            return false;
        }
        
        $keywords = array_map('trim', explode(',', strtolower($this->trigger_value ?? '')));
        $input = strtolower(trim($input));
        
        return in_array($input, $keywords);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByTrigger($query, $type)
    {
        return $query->where('trigger_type', $type);
    }
}

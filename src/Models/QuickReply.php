<?php

namespace WhatsApp\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickReply extends Model
{
    protected $table = 'wa_quick_replies';

    protected $fillable = [
        'customer_id',
        'text',
        'shortcut',
        'category',
        'sort_order',
        'is_global',
        'is_active',
        'created_by_agent_id',
    ];

    protected $casts = [
        'is_global' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'created_by_agent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeGlobal($query)
    {
        return $query->where('is_global', true);
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where(function ($q) use ($customerId) {
            $q->where('customer_id', $customerId)
              ->orWhere('is_global', true);
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}

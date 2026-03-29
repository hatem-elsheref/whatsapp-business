<?php

namespace WhatsApp\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    protected $table = 'wa_templates';

    protected $fillable = [
        'customer_id',
        'phone_number_id',
        'meta_template_id',
        'name',
        'display_name',
        'category',
        'language',
        'status',
        'components',
        'example_data',
        'variable_mappings',
        'allow_category_change',
        'daily_limit',
        'monthly_usage',
        'rejection_reason',
    ];

    protected $casts = [
        'components' => 'array',
        'example_data' => 'array',
        'variable_mappings' => 'array',
        'allow_category_change' => 'boolean',
        'daily_limit' => 'integer',
        'monthly_usage' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class, 'phone_number_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'template_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function getHeaderContent(): ?string
    {
        if (!isset($this->components['header'])) {
            return null;
        }
        
        $header = $this->components['header'];
        
        return match ($header['format'] ?? null) {
            'TEXT' => $header['text'] ?? null,
            'IMAGE' => $header['example']['header_handle'][0] ?? null,
            default => null,
        };
    }

    public function getBodyContent(): ?string
    {
        if (!isset($this->components['body'])) {
            return null;
        }
        
        return $this->components['body']['text'] ?? null;
    }

    public function getFooterContent(): ?string
    {
        return $this->components['footer']['text'] ?? null;
    }

    public function getButtons(): array
    {
        return $this->components['buttons'] ?? [];
    }

    public function getVariables(): array
    {
        $body = $this->getBodyContent();
        if (!$body) {
            return [];
        }
        
        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);
        
        return array_unique($matches[1] ?? []);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByLanguage($query, $language)
    {
        return $query->where('language', $language);
    }
}

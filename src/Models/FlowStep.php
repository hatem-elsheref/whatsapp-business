<?php

namespace WhatsApp\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowStep extends Model
{
    protected $table = 'wa_flow_steps';

    protected $fillable = [
        'flow_id',
        'step_order',
        'step_id',
        'step_type',
        'content',
        'next_step_id',
        'branches',
        'step_timeout_seconds',
        'collected_variable',
        'variable_type',
        'validation_rules',
        'actions',
        'metadata',
    ];

    protected $casts = [
        'content' => 'array',
        'branches' => 'array',
        'validation_rules' => 'array',
        'actions' => 'array',
        'metadata' => 'array',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class, 'flow_id');
    }

    public function nextStep(): BelongsTo
    {
        return $this->belongsTo(FlowStep::class, 'next_step_id');
    }

    public function isMessage(): bool
    {
        return $this->step_type === 'message';
    }

    public function isQuestion(): bool
    {
        return $this->step_type === 'question';
    }

    public function isCondition(): bool
    {
        return $this->step_type === 'condition';
    }

    public function isEnd(): bool
    {
        return $this->step_type === 'end';
    }

    public function isAction(): bool
    {
        return in_array($this->step_type, ['action', 'api_call', 'ticket']);
    }

    public function needsResponse(): bool
    {
        return $this->step_type === 'question' && $this->step_timeout_seconds > 0;
    }

    public function getButtons(): array
    {
        return $this->content['buttons'] ?? [];
    }

    public function getMessageText(): ?string
    {
        return $this->content['text'] ?? null;
    }
}

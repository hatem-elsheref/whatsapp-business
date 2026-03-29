<?php

namespace WhatsApp\Business\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public $conversation,
        public int $agentId
    ) {}
}

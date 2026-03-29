<?php

namespace WhatsApp\Business\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FlowCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public $flowUserData
    ) {}
}

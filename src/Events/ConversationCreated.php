<?php

namespace WhatsApp\Business\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public $conversation
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('customer.' . $this->conversation->customer_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.created';
    }
}

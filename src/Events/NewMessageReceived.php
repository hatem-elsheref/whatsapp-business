<?php

namespace WhatsApp\Business\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessageReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public $message
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('customer.' . $this->message->customer_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.received';
    }
}

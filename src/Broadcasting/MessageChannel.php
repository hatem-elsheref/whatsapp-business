<?php

namespace WhatsApp\Business\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageChannel implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(
        public int $customerId,
        public int $conversationId,
        public array $messageData
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('whatsapp.conversation.' . $this->conversationId),
            new PrivateChannel('whatsapp.customer.' . $this->customerId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.new';
    }

    public function broadcastWith(): array
    {
        return $this->messageData;
    }
}

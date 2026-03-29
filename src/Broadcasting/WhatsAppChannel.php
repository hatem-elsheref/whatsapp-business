<?php

namespace WhatsApp\Business\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class WhatsAppChannel implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(
        public int $customerId,
        public string $event,
        public array $data
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('whatsapp.customer.' . $this->customerId),
        ];
    }

    public function broadcastAs(): string
    {
        return $this->event;
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}

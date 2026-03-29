<?php

namespace WhatsApp\Business\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NotificationChannel implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(
        public int $agentId,
        public int $customerId,
        public array $notificationData
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('whatsapp.agent.' . $this->agentId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.new';
    }

    public function broadcastWith(): array
    {
        return $this->notificationData;
    }
}

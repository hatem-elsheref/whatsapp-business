<?php

namespace WhatsApp\Business\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use WhatsApp\Business\Events\ConversationAssigned;
use WhatsApp\Business\Events\ConversationCreated;
use WhatsApp\Business\Events\FlowCompleted;
use WhatsApp\Business\Events\FlowRequiresAgent;
use WhatsApp\Business\Events\FlowStarted;
use WhatsApp\Business\Events\MessageStatusUpdated;
use WhatsApp\Business\Events\NewMessageReceived;
use WhatsApp\Business\Events\TicketCreated;
use WhatsApp\Business\Events\Listeners\NotificationListener;

class WhatsAppEventServiceProvider extends ServiceProvider
{
    protected $listen = [
        NewMessageReceived::class => [
            [NotificationListener::class, 'handleNewMessage'],
        ],
        ConversationCreated::class => [],
        ConversationAssigned::class => [
            [NotificationListener::class, 'handleConversationAssigned'],
        ],
        MessageStatusUpdated::class => [],
        FlowStarted::class => [],
        FlowCompleted::class => [],
        FlowRequiresAgent::class => [
            [NotificationListener::class, 'handleFlowRequiresAgent'],
        ],
        TicketCreated::class => [
            [NotificationListener::class, 'handleTicketCreated'],
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}

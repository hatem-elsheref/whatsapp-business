<?php

namespace WhatsApp\Business\Tests\Unit\Events;

use WhatsApp\Business\Events\NewMessageReceived;
use WhatsApp\Business\Events\ConversationCreated;
use WhatsApp\Business\Events\ConversationAssigned;
use WhatsApp\Business\Events\FlowStarted;
use WhatsApp\Business\Events\FlowCompleted;
use WhatsApp\Business\Events\FlowRequiresAgent;
use WhatsApp\Business\Events\MessageStatusUpdated;
use WhatsApp\Business\Events\TicketCreated;
use WhatsApp\Business\Tests\TestCase;

class EventsTest extends TestCase
{
    public function test_new_message_received_broadcasts_to_customer_channel(): void
    {
        $message = new \stdClass();
        $message->customer_id = 1;

        $event = new NewMessageReceived($message);

        $this->assertEquals(['customer.1'], $event->broadcastOn());
        $this->assertEquals('message.received', $event->broadcastAs());
    }

    public function test_conversation_created_has_required_properties(): void
    {
        $conversation = new \stdClass();
        $conversation->id = 1;
        $conversation->customer_id = 1;

        $event = new ConversationCreated($conversation);

        $this->assertEquals($conversation, $event->conversation);
        $this->assertIsArray($event->broadcastOn());
    }

    public function test_conversation_assigned_broadcasts_event(): void
    {
        $conversation = new \stdClass();
        $conversation->id = 1;
        $conversation->customer_id = 1;

        $event = new ConversationAssigned($conversation, 5);

        $this->assertEquals(5, $event->agentId);
        $this->assertEquals($conversation, $event->conversation);
    }

    public function test_flow_started_event_structure(): void
    {
        $flowUserData = new \stdClass();
        $flowUserData->id = 1;
        $flowUserData->flow_id = 10;

        $event = new FlowStarted($flowUserData, 'trigger');

        $this->assertEquals($flowUserData, $event->flowUserData);
        $this->assertEquals('trigger', $event->trigger);
    }

    public function test_flow_completed_event_structure(): void
    {
        $flowUserData = new \stdClass();
        $flowUserData->id = 1;

        $event = new FlowCompleted($flowUserData);

        $this->assertEquals($flowUserData, $event->flowUserData);
    }

    public function test_flow_requires_agent_event_includes_message(): void
    {
        $flowUserData = new \stdClass();
        $flowUserData->id = 1;
        $flowUserData->customer_id = 1;

        $event = new FlowRequiresAgent($flowUserData, 'Customer needs help');

        $this->assertEquals('Customer needs help', $event->message);
    }

    public function test_message_status_updated_event_structure(): void
    {
        $message = new \stdClass();
        $message->id = 1;

        $event = new MessageStatusUpdated($message, 'delivered', 'sent');

        $this->assertEquals('delivered', $event->status);
        $this->assertEquals('sent', $event->previousStatus);
    }

    public function test_ticket_created_event_structure(): void
    {
        $ticket = new \stdClass();
        $ticket->id = 1;
        $ticket->ticket_number = 'TKT-001';
        $ticket->customer_id = 1;

        $event = new TicketCreated($ticket);

        $this->assertEquals($ticket, $event->ticket);
    }
}

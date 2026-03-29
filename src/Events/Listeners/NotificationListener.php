<?php

namespace WhatsApp\Business\Events\Listeners;

use Illuminate\Support\Facades\Notification;
use WhatsApp\Business\Models\Notification as WhatsAppNotification;
use WhatsApp\Business\Models\Agent;
use WhatsApp\Business\Events\NewMessageReceived;
use WhatsApp\Business\Events\ConversationAssigned;
use WhatsApp\Business\Events\TicketCreated;
use WhatsApp\Business\Events\FlowRequiresAgent;

class NotificationListener
{
    public function handleNewMessage(NewMessageReceived $event): void
    {
        $message = $event->message;
        $conversation = $message->conversation;
        $customer = $conversation->customer;

        $agents = Agent::where('customer_id', $customer->id)
            ->where('is_active', true)
            ->get();

        foreach ($agents as $agent) {
            $isAssignedToMe = $conversation->assigned_agent_id === $agent->id;

            WhatsAppNotification::create([
                'customer_id' => $customer->id,
                'agent_id' => $agent->id,
                'conversation_id' => $conversation->id,
                'type' => 'new_message',
                'title' => 'رسالة جديدة',
                'body' => $conversation->customer_name ?? $conversation->wa_id,
                'data' => [
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                    'preview' => mb_substr($message->body ?? '', 0, 50),
                ],
                'action_url' => "/conversations/{$conversation->id}",
            ]);
        }
    }

    public function handleConversationAssigned(ConversationAssigned $event): void
    {
        $conversation = $event->conversation;
        $customer = $conversation->customer;

        WhatsAppNotification::create([
            'customer_id' => $customer->id,
            'agent_id' => $event->agentId,
            'conversation_id' => $conversation->id,
            'type' => 'assigned',
            'title' => 'تم إسناد محادثة جديدة',
            'body' => $conversation->customer_name ?? $conversation->wa_id,
            'data' => [
                'conversation_id' => $conversation->id,
            ],
            'action_url' => "/conversations/{$conversation->id}",
        ]);
    }

    public function handleTicketCreated(TicketCreated $event): void
    {
        $ticket = $event->ticket;
        $customer = $ticket->customer;

        $agents = Agent::where('customer_id', $customer->id)
            ->where('is_active', true)
            ->get();

        foreach ($agents as $agent) {
            WhatsAppNotification::create([
                'customer_id' => $customer->id,
                'agent_id' => $agent->id,
                'ticket_id' => $ticket->id,
                'type' => 'ticket_created',
                'title' => 'تذكرة جديدة #' . $ticket->ticket_number,
                'body' => $ticket->subject,
                'data' => [
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'priority' => $ticket->priority,
                ],
                'action_url' => "/tickets/{$ticket->id}",
            ]);
        }
    }

    public function handleFlowRequiresAgent(FlowRequiresAgent $event): void
    {
        $flowUserData = $event->flowUserData;
        $customer = $flowUserData->customer;
        $conversation = $flowUserData->conversation;

        $agents = Agent::where('customer_id', $customer->id)
            ->where('is_active', true)
            ->get();

        foreach ($agents as $agent) {
            WhatsAppNotification::create([
                'customer_id' => $customer->id,
                'agent_id' => $agent->id,
                'conversation_id' => $conversation->id,
                'type' => 'flow_started',
                'title' => 'تدفق يحتاج تدخل',
                'body' => $event->message,
                'data' => [
                    'flow_id' => $flowUserData->flow_id,
                    'conversation_id' => $conversation->id,
                    'variables' => $flowUserData->variables,
                ],
                'action_url' => "/conversations/{$conversation->id}",
            ]);
        }
    }
}

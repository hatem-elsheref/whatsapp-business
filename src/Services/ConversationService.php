<?php

namespace WhatsApp\Business\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use WhatsApp\Business\Models\Conversation;
use WhatsApp\Business\Models\Customer;
use WhatsApp\Business\Models\PhoneNumber;
use WhatsApp\Business\Models\Message;
use WhatsApp\Business\Models\Flow;
use WhatsApp\Business\Models\FlowUserData;
use WhatsApp\Business\Events\NewMessageReceived;

class ConversationService
{
    public function __construct(
        private WhatsAppCloudService $whatsAppService,
        private FlowEngine $flowEngine
    ) {}

    public function getOrCreateConversation(
        Customer $customer,
        PhoneNumber $phoneNumber,
        string $waId,
        string $customerName = null,
        string $source = null
    ): Conversation {
        $conversation = Conversation::firstOrCreate(
            [
                'phone_number_id' => $phoneNumber->id,
                'wa_id' => $waId,
            ],
            [
                'customer_id' => $customer->id,
                'customer_name' => $customerName,
                'source' => $source,
                'status' => 'active',
                'window_expires_at' => now()->addHours(24),
            ]
        );

        if ($conversation->wasRecentlyCreated) {
            Event::dispatch(new \WhatsApp\Business\Events\ConversationCreated($conversation));
        }

        return $conversation;
    }

    public function updateConversationLastMessage(Conversation $conversation, Message $message): void
    {
        $conversation->update([
            'last_message_id' => $message->meta_message_id ?? $message->id,
            'last_message_at' => $message->created_at,
            'last_message_preview' => $this->getMessagePreview($message),
            'last_message_direction' => $message->direction,
        ]);
    }

    public function handleIncomingMessage(array $webhookData): ?Message
    {
        $entry = $webhookData['entry'][0] ?? null;
        $changes = $entry['changes'][0] ?? null;
        $value = $changes['value'] ?? null;
        
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
        $waId = $value['contacts'][0]['wa_id'] ?? null;
        $messages = $value['messages'] ?? [];
        
        if (!$phoneNumberId || !$waId || empty($messages)) {
            return null;
        }

        $phoneNumber = PhoneNumber::where('phone_number_id', $phoneNumberId)->first();
        if (!$phoneNumber) {
            Log::warning('Phone number not found for incoming message', [
                'phone_number_id' => $phoneNumberId,
            ]);
            return null;
        }

        $customer = $phoneNumber->customer;
        
        $contact = $value['contacts'][0];
        $customerName = $contact['profile']['name'] ?? null;

        $conversation = $this->getOrCreateConversation(
            $customer,
            $phoneNumber,
            $waId,
            $customerName,
            'incoming'
        );

        $messageData = $messages[0];
        $message = $this->storeIncomingMessage($conversation, $phoneNumber, $messageData);

        $conversation->incrementUnread();
        $conversation->window_expires_at = now()->addHours(24);
        $conversation->save();

        $this->updateConversationLastMessage($conversation, $message);

        Event::dispatch(new NewMessageReceived($message));

        $this->processFlowTrigger($conversation, $message);

        return $message;
    }

    public function sendMessage(
        Conversation $conversation,
        string $body,
        ?int $agentId = null,
        ?string $replyToMessageId = null,
        ?string $mediaUrl = null,
        ?string $mediaType = 'image'
    ): Message {
        $customer = $conversation->customer;
        $phoneNumber = $conversation->phoneNumber;

        $message = Message::create([
            'customer_id' => $customer->id,
            'phone_number_id' => $phoneNumber->id,
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => $mediaUrl ? $mediaType : 'text',
            'body' => $body,
            'media_url' => $mediaUrl,
            'status' => 'pending',
            'sent_by_agent_id' => $agentId,
        ]);

        try {
            if ($mediaUrl) {
                $response = match ($mediaType) {
                    'image' => $this->whatsAppService->sendImageMessage(
                        $customer,
                        $phoneNumber,
                        $conversation->wa_id,
                        $mediaUrl,
                        $body ?: null
                    ),
                    'video' => $this->whatsAppService->sendVideoMessage(
                        $customer,
                        $phoneNumber,
                        $conversation->wa_id,
                        $mediaUrl,
                        $body ?: null
                    ),
                    'document' => $this->whatsAppService->sendDocumentMessage(
                        $customer,
                        $phoneNumber,
                        $conversation->wa_id,
                        $mediaUrl,
                        $body
                    ),
                    'audio' => $this->whatsAppService->sendAudioMessage(
                        $customer,
                        $phoneNumber,
                        $conversation->wa_id,
                        $mediaUrl
                    ),
                    default => $this->whatsAppService->sendTextMessage(
                        $customer,
                        $phoneNumber,
                        $conversation->wa_id,
                        $body,
                        $replyToMessageId
                    ),
                };
            } else {
                $response = $this->whatsAppService->sendTextMessage(
                    $customer,
                    $phoneNumber,
                    $conversation->wa_id,
                    $body,
                    $replyToMessageId
                );
            }

            $message->update([
                'meta_message_id' => $response['messages'][0]['id'] ?? null,
                'status' => 'sent',
            ]);

            $conversation->update([
                'last_message_id' => $response['messages'][0]['id'] ?? $message->id,
                'last_message_at' => now(),
                'last_message_preview' => $this->getMessagePreview($message),
                'last_message_direction' => 'outbound',
            ]);

        } catch (\Exception $e) {
            $message->markAsFailed($e->getMessage());
            Log::error('Failed to send message', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $message;
    }

    public function archiveConversation(Conversation $conversation): void
    {
        $conversation->update(['status' => 'archived']);
    }

    public function blockConversation(Conversation $conversation): void
    {
        $conversation->update(['status' => 'blocked']);
    }

    public function assignConversation(Conversation $conversation, ?int $agentId): void
    {
        $conversation->update(['assigned_agent_id' => $agentId]);
        
        if ($agentId) {
            Event::dispatch(new \WhatsApp\Business\Events\ConversationAssigned($conversation, $agentId));
        }
    }

    public function markConversationAsRead(Conversation $conversation): void
    {
        $conversation->messages()
            ->where('direction', 'inbound')
            ->where('status', '!=', 'read')
            ->update(['status' => 'read']);

        $conversation->update(['unread_count' => 0]);

        $lastInboundMessage = $conversation->messages()
            ->where('direction', 'inbound')
            ->latest()
            ->first();

        if ($lastInboundMessage) {
            try {
                $customer = $conversation->customer;
                $phoneNumber = $conversation->phoneNumber;
                $this->whatsAppService->markMessageAsRead(
                    $lastInboundMessage->meta_message_id,
                    $customer,
                    $phoneNumber
                );
            } catch (\Exception $e) {
                Log::warning('Failed to mark message as read on WhatsApp', [
                    'message_id' => $lastInboundMessage->meta_message_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function storeIncomingMessage(
        Conversation $conversation,
        PhoneNumber $phoneNumber,
        array $messageData
    ): Message {
        $type = $this->mapMessageType($messageData['type'] ?? 'text');
        $body = $messageData['text']['body'] ?? 
                $messageData['image']['caption'] ?? 
                $messageData['video']['caption'] ?? 
                $messageData['document']['caption'] ?? 
                null;

        return Message::create([
            'customer_id' => $conversation->customer_id,
            'phone_number_id' => $phoneNumber->id,
            'conversation_id' => $conversation->id,
            'meta_message_id' => $messageData['id'] ?? null,
            'direction' => 'inbound',
            'type' => $type,
            'body' => $body,
            'media_url' => $messageData['image']['id'] ?? 
                          $messageData['video']['id'] ?? 
                          $messageData['audio']['id'] ?? 
                          $messageData['document']['id'] ?? null,
            'media_mime_type' => $this->getMediaMimeType($messageData),
            'status' => 'delivered',
            'buttons' => isset($messageData['interactive']) ? $messageData['interactive'] : null,
            'sent_at' => isset($messageData['timestamp']) 
                ? \Carbon\Carbon::createFromTimestamp($messageData['timestamp']) 
                : now(),
        ]);
    }

    private function processFlowTrigger(Conversation $conversation, Message $message): void
    {
        if ($conversation->assignedAgent) {
            return;
        }

        $flows = Flow::active()
            ->where('customer_id', $conversation->customer_id)
            ->where(function ($query) use ($conversation) {
                $query->whereNull('phone_number_id')
                    ->orWhere('phone_number_id', $conversation->phone_number_id);
            })
            ->get();

        foreach ($flows as $flow) {
            if ($flow->matchesKeyword($message->body)) {
                $this->flowEngine->startFlow($flow, $conversation);
                break;
            }
        }
    }

    private function mapMessageType(string $type): string
    {
        return match ($type) {
            'text' => 'text',
            'image' => 'image',
            'video' => 'video',
            'audio' => 'audio',
            'document' => 'document',
            'sticker' => 'sticker',
            'location' => 'location',
            'contacts' => 'contact',
            'interactive' => 'interactive',
            default => 'unknown',
        };
    }

    private function getMediaMimeType(array $messageData): ?string
    {
        if (isset($messageData['image'])) return 'image/jpeg';
        if (isset($messageData['video'])) return 'video/mp4';
        if (isset($messageData['audio'])) return 'audio/mpeg';
        if (isset($messageData['document'])) return 'application/pdf';
        return null;
    }

    private function getMessagePreview(Message $message): string
    {
        if ($message->type !== 'text' && $message->body) {
            return "[{$message->type}] {$message->body}";
        }
        return $message->body ?? '';
    }
}

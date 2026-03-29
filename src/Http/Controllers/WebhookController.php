<?php

namespace WhatsApp\Business\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use WhatsApp\Business\Services\ConversationService;
use WhatsApp\Business\Services\WhatsAppCloudService;
use WhatsApp\Business\Models\Message;
use WhatsApp\Business\Models\Conversation;
use WhatsApp\Business\Events\NewMessageReceived;

class WebhookController
{
    public function __construct(
        private ConversationService $conversationService,
        private WhatsAppCloudService $whatsAppService
    ) {}

    public function verify(Request $request): JsonResponse
    {
        $mode = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        $verifyToken = config('whatsapp.webhook.verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('Webhook verified successfully');
            return response()->json($challenge, 200);
        }

        Log::warning('Webhook verification failed', [
            'mode' => $mode,
            'token_match' => $token === $verifyToken,
        ]);

        return response()->json('Forbidden', 403);
    }

    public function handle(Request $request): JsonResponse
    {
        try {
            $body = $request->all();
            
            Log::info('Webhook received', ['entry_count' => count($body['entry'] ?? [])]);

            if (isset($body['entry'][0]['changes'])) {
                foreach ($body['entry'] as $entry) {
                    $this->processEntry($entry);
                }
            }

            return response()->json('OK', 200);
        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json('Error', 500);
        }
    }

    private function processEntry(array $entry): void
    {
        $changes = $entry['changes'] ?? [];

        foreach ($changes as $change) {
            $value = $change['value'] ?? [];

            if (isset($value['statuses'])) {
                $this->processStatusUpdates($value);
            }

            if (isset($value['messages'])) {
                $this->processMessages($value);
            }
        }
    }

    private function processStatusUpdates(array $value): void
    {
        $statuses = $value['statuses'] ?? [];

        foreach ($statuses as $status) {
            $messageId = $status['id'] ?? null;
            $conversationId = $status['conversation_id'] ?? null;
            $statusValue = $status['status'] ?? null;
            $recipientId = $status['recipient_id'] ?? null;
            $timestamp = $status['timestamp'] ?? null;

            if (!$messageId) {
                continue;
            }

            $message = Message::where('meta_message_id', $messageId)->first();

            if (!$message) {
                continue;
            }

            $this->updateMessageStatus($message, $statusValue, $status);

            Log::info('Message status updated', [
                'message_id' => $messageId,
                'status' => $statusValue,
            ]);
        }
    }

    private function processMessages(array $value): void
    {
        $messages = $value['messages'] ?? [];

        foreach ($messages as $messageData) {
            if ($messageData['type'] === 'text' || 
                $messageData['type'] === 'image' ||
                $messageData['type'] === 'video' ||
                $messageData['type'] === 'audio' ||
                $messageData['type'] === 'document' ||
                $messageData['type'] === 'interactive') {
                
                $this->conversationService->handleIncomingMessage([
                    'entry' => [
                        [
                            'changes' => [
                                [
                                    'value' => $value
                                ]
                            ]
                        ]
                    ]
                ]);
            }

            if ($messageData['type'] === 'reaction') {
                Log::info('Reaction received', ['message' => $messageData]);
            }

            if ($messageData['type'] === 'sticker') {
                Log::info('Sticker received', ['message' => $messageData]);
            }
        }
    }

    private function updateMessageStatus(Message $message, string $status, array $statusData): void
    {
        $timestamp = isset($statusData['timestamp']) 
            ? \Carbon\Carbon::createFromTimestamp($statusData['timestamp'])
            : now();

        $updateData = ['status' => $status];

        switch ($status) {
            case 'sent':
                $updateData['sent_at'] = $timestamp;
                break;

            case 'delivered':
                $updateData['delivered_at'] = $timestamp;
                break;

            case 'read':
                $updateData['read_at'] = $timestamp;
                break;

            case 'failed':
                $updateData['failed_at'] = $timestamp;
                $updateData['error_message'] = $statusData['errors'][0]['error_data']['details'] ?? 'Message failed';
                break;
        }

        $message->update($updateData);

        if (in_array($status, ['delivered', 'read'])) {
            $conversation = $message->conversation;
            if ($conversation) {
                $conversation->touch();
            }
        }

        event(new \WhatsApp\Business\Events\MessageStatusUpdated($message));
    }
}

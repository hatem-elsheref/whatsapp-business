<?php

namespace WhatsApp\Business\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use WhatsApp\Business\Models\Message;
use WhatsApp\Business\Models\Customer;
use WhatsApp\Business\Models\PhoneNumber;
use WhatsApp\Business\Services\WhatsAppCloudService;

class SendMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public Message $message,
        public Customer $customer,
        public PhoneNumber $phoneNumber
    ) {}

    public function handle(WhatsAppCloudService $whatsAppService): void
    {
        if ($this->message->status !== 'pending') {
            return;
        }

        try {
            if ($this->message->type === 'text') {
                $response = $whatsAppService->sendTextMessage(
                    $this->customer,
                    $this->phoneNumber,
                    $this->message->conversation->wa_id,
                    $this->message->body
                );
            } elseif ($this->message->media_url) {
                $response = match ($this->message->type) {
                    'image' => $whatsAppService->sendImageMessage(
                        $this->customer,
                        $this->phoneNumber,
                        $this->message->conversation->wa_id,
                        $this->message->media_url,
                        $this->message->body
                    ),
                    'video' => $whatsAppService->sendVideoMessage(
                        $this->customer,
                        $this->phoneNumber,
                        $this->message->conversation->wa_id,
                        $this->message->media_url,
                        $this->message->body
                    ),
                    'document' => $whatsAppService->sendDocumentMessage(
                        $this->customer,
                        $this->phoneNumber,
                        $this->message->conversation->wa_id,
                        $this->message->media_url,
                        $this->message->body
                    ),
                    'audio' => $whatsAppService->sendAudioMessage(
                        $this->customer,
                        $this->phoneNumber,
                        $this->message->conversation->wa_id,
                        $this->message->media_url
                    ),
                    default => $whatsAppService->sendTextMessage(
                        $this->customer,
                        $this->phoneNumber,
                        $this->message->conversation->wa_id,
                        $this->message->body
                    ),
                };
            } else {
                return;
            }

            $this->message->update([
                'meta_message_id' => $response['messages'][0]['id'] ?? null,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

        } catch (\Exception $e) {
            $this->message->update([
                'status' => 'failed_temporary',
                'error_message' => $e->getMessage(),
                'retry_count' => $this->message->retry_count + 1,
            ]);

            if ($this->message->retry_count >= $this->tries) {
                $this->message->update(['status' => 'failed']);
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->message->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}

<?php

namespace WhatsApp\Business\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use WhatsApp\Business\Models\Customer;
use WhatsApp\Business\Models\PhoneNumber;
use WhatsApp\Business\Models\Template;

class WhatsAppCloudService
{
    private Client $client;
    private string $apiVersion;
    private string $graphUrl;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
        
        $this->apiVersion = config('whatsapp.meta.api_version', 'v18.0');
        $this->graphUrl = config('whatsapp.meta.graph_url', 'https://graph.facebook.com');
    }

    public function sendTextMessage(
        Customer $customer,
        PhoneNumber $phoneNumber,
        string $recipientNumber,
        string $message,
        ?string $replyMessageId = null
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhoneNumber($recipientNumber),
            'type' => 'text',
            'text' => [
                'preview_url' => $this->containsUrl($message),
                'body' => $message,
            ],
        ];

        if ($replyMessageId) {
            $payload['context']['message_id'] = $replyMessageId;
        }

        return $this->sendMessage($customer, $phoneNumber, $payload);
    }

    public function sendImageMessage(
        Customer $customer,
        PhoneNumber $phoneNumber,
        string $recipientNumber,
        string $imageUrl,
        ?string $caption = null,
        ?string $replyMessageId = null
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhoneNumber($recipientNumber),
            'type' => 'image',
            'image' => [
                'link' => $imageUrl,
            ],
        ];

        if ($caption) {
            $payload['image']['caption'] = $caption;
        }

        if ($replyMessageId) {
            $payload['context']['message_id'] = $replyMessageId;
        }

        return $this->sendMessage($customer, $phoneNumber, $payload);
    }

    public function sendDocumentMessage(
        Customer $customer,
        PhoneNumber $phoneNumber,
        string $recipientNumber,
        string $documentUrl,
        string $filename,
        ?string $caption = null
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhoneNumber($recipientNumber),
            'type' => 'document',
            'document' => [
                'link' => $documentUrl,
                'filename' => $filename,
            ],
        ];

        if ($caption) {
            $payload['document']['caption'] = $caption;
        }

        return $this->sendMessage($customer, $phoneNumber, $payload);
    }

    public function sendAudioMessage(
        Customer $customer,
        PhoneNumber $phoneNumber,
        string $recipientNumber,
        string $audioUrl
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhoneNumber($recipientNumber),
            'type' => 'audio',
            'audio' => [
                'link' => $audioUrl,
            ],
        ];

        return $this->sendMessage($customer, $phoneNumber, $payload);
    }

    public function sendVideoMessage(
        Customer $customer,
        PhoneNumber $phoneNumber,
        string $recipientNumber,
        string $videoUrl,
        ?string $caption = null
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhoneNumber($recipientNumber),
            'type' => 'video',
            'video' => [
                'link' => $videoUrl,
            ],
        ];

        if ($caption) {
            $payload['video']['caption'] = $caption;
        }

        return $this->sendMessage($customer, $phoneNumber, $payload);
    }

    public function sendTemplateMessage(
        Customer $customer,
        PhoneNumber $phoneNumber,
        string $recipientNumber,
        Template $template,
        array $variables = [],
        ?string $replyMessageId = null
    ): array {
        $components = $this->buildTemplateComponents($template, $variables);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhoneNumber($recipientNumber),
            'type' => 'template',
            'template' => [
                'name' => $template->name,
                'language' => [
                    'code' => $template->language,
                ],
                'components' => $components,
            ],
        ];

        if ($replyMessageId) {
            $payload['context']['message_id'] = $replyMessageId;
        }

        return $this->sendMessage($customer, $phoneNumber, $payload);
    }

    public function sendInteractiveButtonsMessage(
        Customer $customer,
        PhoneNumber $phoneNumber,
        string $recipientNumber,
        string $bodyText,
        array $buttons,
        ?string $headerText = null,
        ?string $footerText = null
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhoneNumber($recipientNumber),
            'type' => 'interactive',
            'interactive' => [
                'type' => 'buttons',
                'body' => [
                    'text' => $bodyText,
                ],
                'action' => [
                    'buttons' => array_map(function ($button) {
                        return [
                            'type' => 'reply',
                            'reply' => [
                                'id' => $button['id'],
                                'title' => $button['title'],
                            ],
                        ];
                    }, $buttons),
                ],
            ],
        ];

        if ($headerText) {
            $payload['interactive']['header'] = [
                'type' => 'text',
                'text' => $headerText,
            ];
        }

        if ($footerText) {
            $payload['interactive']['footer'] = [
                'text' => $footerText,
            ];
        }

        return $this->sendMessage($customer, $phoneNumber, $payload);
    }

    public function sendInteractiveListMessage(
        Customer $customer,
        PhoneNumber $phoneNumber,
        string $recipientNumber,
        string $bodyText,
        string $buttonText,
        array $sections,
        ?string $headerText = null,
        ?string $footerText = null
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhoneNumber($recipientNumber),
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => [
                    'text' => $bodyText,
                ],
                'action' => [
                    'button' => $buttonText,
                    'sections' => array_map(function ($section) {
                        return [
                            'title' => $section['title'] ?? '',
                            'rows' => array_map(function ($row) {
                                return [
                                    'id' => $row['id'],
                                    'title' => $row['title'],
                                    'description' => $row['description'] ?? '',
                                ];
                            }, $section['rows']),
                        ];
                    }, $sections),
                ],
            ],
        ];

        if ($headerText) {
            $payload['interactive']['header'] = [
                'type' => 'text',
                'text' => $headerText,
            ];
        }

        if ($footerText) {
            $payload['interactive']['footer'] = [
                'text' => $footerText,
            ];
        }

        return $this->sendMessage($customer, $phoneNumber, $payload);
    }

    public function markMessageAsRead(string $messageId, Customer $customer, PhoneNumber $phoneNumber): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'message_id' => $messageId,
        ];

        return $this->makeRequest(
            'POST',
            "/{$this->apiVersion}/{$phoneNumber->phone_number_id}/messages",
            $customer->access_token,
            $payload
        );
    }

    public function getBusinessAccountInfo(string $businessAccountId, Customer $customer): array
    {
        return $this->makeRequest(
            'GET',
            "/{$this->apiVersion}/{$businessAccountId}",
            $customer->access_token
        );
    }

    public function getPhoneNumbers(string $wabaId, Customer $customer): array
    {
        $response = $this->makeRequest(
            'GET',
            "/{$this->apiVersion}/{$wabaId}/phone_numbers",
            $customer->access_token
        );

        return $response['data'] ?? [];
    }

    public function getPhoneNumberDetails(string $phoneNumberId, Customer $customer): array
    {
        return $this->makeRequest(
            'GET',
            "/{$this->apiVersion}/{$phoneNumberId}",
            $customer->access_token
        );
    }

    public function registerPhoneNumberWebhook(
        PhoneNumber $phoneNumber,
        Customer $customer,
        string $webhookUrl,
        string $verifyToken
    ): array {
        $payload = [
            'webhook_url' => $webhookUrl,
            'verify_token' => $verifyToken,
        ];

        return $this->makeRequest(
            'POST',
            "/{$this->apiVersion}/{$phoneNumber->phone_number_id}/webhooks",
            $customer->access_token,
            $payload
        );
    }

    public function getTemplates(string $wabaId, Customer $customer): array
    {
        $response = $this->makeRequest(
            'GET',
            "/{$this->apiVersion}/{$wabaId}/message_templates",
            $customer->access_token
        );

        return $response['data'] ?? [];
    }

    public function getTemplateDetails(string $templateId, Customer $customer): array
    {
        return $this->makeRequest(
            'GET',
            "/{$this->apiVersion}/{$templateId}",
            $customer->access_token
        );
    }

    public function downloadMedia(string $mediaUrl, Customer $customer): ?string
    {
        try {
            $response = $this->client->get($mediaUrl, [
                'headers' => [
                    'Authorization' => "Bearer {$customer->access_token}",
                ],
            ]);

            $content = $response->getBody()->getContents();
            $headers = $response->getHeaders();
            
            $mimeType = $headers['Content-Type'][0] ?? 'application/octet-stream';
            $extension = $this->getExtensionFromMimeType($mimeType);
            
            $filename = uniqid('media_') . '.' . $extension;
            $path = storage_path('app/whatsapp/media/' . $filename);
            
            \Illuminate\Support\Facades\File::makeDirectory(dirname($path), 0755, true, true);
            \Illuminate\Support\Facades\File::put($path, $content);
            
            return $path;
        } catch (GuzzleException $e) {
            Log::error('WhatsApp media download failed', [
                'media_url' => $mediaUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function sendMessage(Customer $customer, PhoneNumber $phoneNumber, array $payload): array
    {
        return $this->makeRequest(
            'POST',
            "/{$this->apiVersion}/{$phoneNumber->phone_number_id}/messages",
            $customer->access_token,
            $payload
        );
    }

    private function makeRequest(
        string $method,
        string $endpoint,
        string $accessToken,
        ?array $payload = null
    ): array {
        $url = $this->graphUrl . $endpoint;
        
        $options = [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ],
        ];

        if ($payload) {
            $options['json'] = $payload;
        }

        try {
            $response = $this->client->request($method, $url, $options);
            $body = json_decode($response->getBody()->getContents(), true);
            
            Log::info('WhatsApp API request successful', [
                'method' => $method,
                'endpoint' => $endpoint,
                'response' => $body,
            ]);
            
            return $body ?? [];
        } catch (GuzzleException $e) {
            $errorBody = $e->getResponse() 
                ? json_decode($e->getResponse()->getBody()->getContents(), true) 
                : null;
            
            Log::error('WhatsApp API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'error_body' => $errorBody,
            ]);

            throw new \Exception(
                $errorBody['error']['message'] ?? $e->getMessage(),
                $errorBody['error']['code'] ?? $e->getCode()
            );
        }
    }

    private function buildTemplateComponents(Template $template, array $variables): array
    {
        $components = [];
        $componentsArray = $template->components ?? [];

        foreach (['header', 'body', 'footer', 'buttons'] as $type) {
            if (!isset($componentsArray[$type])) {
                continue;
            }

            $component = $componentsArray[$type];

            if ($type === 'body' && isset($component['example'])) {
                unset($component['example']);
            }

            if ($type === 'body' && !empty($variables)) {
                $bodyText = $component['text'] ?? '';
                $bodyText = $this->replaceTemplateVariables($bodyText, $variables);
                $component['text'] = $bodyText;
            }

            if ($type === 'header' && $component['format'] === 'TEXT' && isset($variables['header'])) {
                $component['text'] = $this->replaceTemplateVariables($component['text'] ?? '', ['header']);
            }

            if ($type === 'buttons') {
                foreach ($component as &$button) {
                    if (isset($button['example']) && isset($variables['button'])) {
                        $button['text'] = $this->replaceTemplateVariables(
                            $button['text'] ?? '',
                            ['button']
                        );
                    }
                }
            }

            $components[] = $component;
        }

        return $components;
    }

    private function replaceTemplateVariables(string $text, array $variables): string
    {
        $replacements = [];
        
        if (isset($variables['header'])) {
            $replacements['{{1}}'] = $variables['header'];
        }
        
        if (isset($variables['body'])) {
            for ($i = 1; $i <= 10; $i++) {
                $placeholder = '{{' . $i . '}}';
                if (strpos($text, $placeholder) !== false && isset($variables['body'][$i - 1])) {
                    $replacements[$placeholder] = $variables['body'][$i - 1];
                }
            }
        }

        foreach ($variables as $key => $value) {
            if (is_string($value)) {
                $index = array_search($key, array_keys($variables)) + 1;
                $placeholder = '{{' . $index . '}}';
                $replacements[$placeholder] = $value;
            }
        }

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    private function formatPhoneNumber(string $number): string
    {
        return preg_replace('/[^0-9+]/', '', $number);
    }

    private function containsUrl(string $text): bool
    {
        return (bool) preg_match('/https?:\/\/[^\s]+/', $text);
    }

    private function getExtensionFromMimeType(string $mimeType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'application/pdf' => 'pdf',
        ];

        return $map[$mimeType] ?? 'bin';
    }
}

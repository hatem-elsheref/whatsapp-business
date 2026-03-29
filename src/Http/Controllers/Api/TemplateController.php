<?php

namespace WhatsApp\Business\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use WhatsApp\Business\Models\Template;
use WhatsApp\Business\Models\Conversation;
use WhatsApp\Business\Models\Customer;
use WhatsApp\Business\Services\WhatsAppCloudService;
use WhatsApp\Business\Services\OAuthService;

class TemplateController
{
    public function __construct(
        private WhatsAppCloudService $whatsAppService,
        private OAuthService $oauthService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $query = Template::where('customer_id', $customer->id);

        if ($request->has('phone_number_id')) {
            $query->where('phone_number_id', $request->phone_number_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('language')) {
            $query->where('language', $request->language);
        }

        $templates = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'templates' => $templates,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $template = Template::where('customer_id', $customer->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'template' => $template,
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        if ($customer->isTokenExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Token expired. Please reconnect your account.',
            ], 401);
        }

        $phoneNumbers = $customer->phoneNumbers()->where('is_active', true)->get();
        $syncedCount = 0;

        foreach ($phoneNumbers as $phoneNumber) {
            $metaTemplates = $this->whatsAppService->getTemplates($phoneNumber->waba_id, $customer);

            foreach ($metaTemplates as $metaTemplate) {
                Template::updateOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'meta_template_id' => $metaTemplate['id'],
                    ],
                    [
                        'phone_number_id' => $phoneNumber->id,
                        'name' => $metaTemplate['name'],
                        'category' => $metaTemplate['category'] ?? 'utility',
                        'language' => $metaTemplate['language'] ?? 'en',
                        'status' => $metaTemplate['status'] ?? 'pending',
                        'components' => $metaTemplate['components'] ?? [],
                    ]
                );
                $syncedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Synced {$syncedCount} templates",
            'synced_count' => $syncedCount,
        ]);
    }

    public function send(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'wa_id' => 'required|string',
            'variables' => 'nullable|array',
            'phone_number_id' => 'nullable|exists:wa_phone_numbers,id',
        ]);

        $agent = $request->user();
        $customer = $agent->customer;

        $template = Template::where('customer_id', $customer->id)
            ->findOrFail($id);

        if (!$template->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Template is not approved',
            ], 400);
        }

        $phoneNumber = $request->phone_number_id 
            ? $customer->phoneNumbers()->find($request->phone_number_id)
            : $customer->phoneNumbers()->where('is_default', true)->first();

        if (!$phoneNumber) {
            $phoneNumber = $customer->phoneNumbers()->first();
        }

        if (!$phoneNumber) {
            return response()->json([
                'success' => false,
                'message' => 'No phone number available',
            ], 400);
        }

        $conversation = Conversation::firstOrCreate(
            [
                'phone_number_id' => $phoneNumber->id,
                'wa_id' => $request->wa_id,
            ],
            [
                'customer_id' => $customer->id,
                'status' => 'active',
                'window_expires_at' => now()->addHours(24),
            ]
        );

        try {
            $response = $this->whatsAppService->sendTemplateMessage(
                $customer,
                $phoneNumber,
                $request->wa_id,
                $template,
                $request->variables ?? []
            );

            $message = \WhatsApp\Business\Models\Message::create([
                'customer_id' => $customer->id,
                'phone_number_id' => $phoneNumber->id,
                'conversation_id' => $conversation->id,
                'meta_message_id' => $response['messages'][0]['id'] ?? null,
                'direction' => 'outbound',
                'type' => 'template',
                'body' => $template->name,
                'status' => 'sent',
                'template_id' => $template->id,
                'sent_by_agent_id' => $agent->id,
            ]);

            $template->increment('monthly_usage');

            return response()->json([
                'success' => true,
                'message' => 'Template sent successfully',
                'data' => [
                    'message_id' => $response['messages'][0]['id'] ?? null,
                    'conversation_id' => $conversation->id,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send template: ' . $e->getMessage(),
            ], 500);
        }
    }
}

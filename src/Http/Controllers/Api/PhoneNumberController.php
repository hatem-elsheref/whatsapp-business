<?php

namespace WhatsApp\Business\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use WhatsApp\Business\Models\PhoneNumber;
use WhatsApp\Business\Services\OAuthService;
use WhatsApp\Business\Services\WhatsAppCloudService;

class PhoneNumberController
{
    public function __construct(
        private OAuthService $oauthService,
        private WhatsAppCloudService $whatsAppService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $phoneNumbers = PhoneNumber::where('customer_id', $customer->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'phone_numbers' => $phoneNumbers,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $phoneNumber = PhoneNumber::where('customer_id', $customer->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'phone_number' => $phoneNumber,
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

        $phoneNumbers = $this->oauthService->syncPhoneNumbers($customer);

        return response()->json([
            'success' => true,
            'message' => "Synced " . count($phoneNumbers) . " phone numbers",
            'phone_numbers' => $phoneNumbers,
        ]);
    }

    public function testWebhook(Request $request, int $id): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $phoneNumber = PhoneNumber::where('customer_id', $customer->id)
            ->findOrFail($id);

        try {
            $webhookUrl = config('app.url') . '/api/wa/webhook';
            $verifyToken = config('whatsapp.webhook.verify_token');

            $response = $this->whatsAppService->registerPhoneNumberWebhook(
                $phoneNumber,
                $customer,
                $webhookUrl,
                $verifyToken
            );

            $phoneNumber->update([
                'webhook_verified' => true,
                'webhook_url' => $webhookUrl,
                'webhook_verify_token' => $verifyToken,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook configured successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to configure webhook: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function setDefault(Request $request, int $id): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $phoneNumber = PhoneNumber::where('customer_id', $customer->id)
            ->findOrFail($id);

        PhoneNumber::where('customer_id', $customer->id)
            ->where('id', '!=', $id)
            ->update(['is_default' => false]);

        $phoneNumber->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Default phone number updated',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        if (!$agent->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can remove phone numbers',
            ], 403);
        }

        $phoneNumber = PhoneNumber::where('customer_id', $customer->id)
            ->findOrFail($id);

        $phoneNumber->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Phone number disconnected',
        ]);
    }
}

<?php

namespace WhatsApp\Business\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use WhatsApp\Business\Services\OAuthService;
use WhatsApp\Business\Models\Customer;
use WhatsApp\Business\Models\PhoneNumber;

class OAuthController
{
    public function __construct(
        private OAuthService $oauthService
    ) {}

    public function redirectToProvider(Request $request): JsonResponse
    {
        $state = Str::random(40);
        
        session(['oauth_state' => $state]);
        
        $redirectUri = config('app.url') . '/api/wa/oauth/callback';
        
        $url = $this->oauthService->getAuthorizationUrl($redirectUri, $state);
        
        return response()->json(['url' => $url]);
    }

    public function handleCallback(Request $request): JsonResponse
    {
        try {
            $code = $request->get('code');
            $state = $request->get('state');
            $error = $request->get('error');

            if ($error) {
                Log::error('OAuth error', ['error' => $error]);
                return response()->json([
                    'success' => false,
                    'message' => 'OAuth authentication failed',
                    'error' => $error,
                ], 400);
            }

            $savedState = session('oauth_state');
            if ($state !== $savedState) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid state parameter',
                ], 400);
            }

            $redirectUri = config('app.url') . '/api/wa/oauth/callback';
            
            $shortLivedToken = $this->oauthService->exchangeCodeForToken($code, $redirectUri);
            
            if (!isset($shortLivedToken['access_token'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to get access token',
                ], 500);
            }

            $longLivedToken = $this->oauthService->getLongLivedToken($shortLivedToken['access_token']);
            
            $userInfo = $this->oauthService->getUserInfo($longLivedToken['access_token'] ?? $shortLivedToken['access_token']);

            $customer = $this->oauthService->createOrUpdateCustomer(
                $userInfo,
                $shortLivedToken['access_token'],
                $longLivedToken['access_token'] ?? $shortLivedToken['access_token'],
                $longLivedToken['expires_in'] ?? 5184000
            );

            $phoneNumbers = $this->oauthService->syncPhoneNumbers($customer);

            session()->forget('oauth_state');

            Log::info('OAuth completed successfully', [
                'customer_id' => $customer->id,
                'phone_numbers_count' => count($phoneNumbers),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Connected successfully',
                'customer' => [
                    'id' => $customer->id,
                    'business_name' => $customer->business_name,
                    'phone_numbers_count' => count($phoneNumbers),
                ],
                'phone_numbers' => collect($phoneNumbers)->map(fn($pn) => [
                    'id' => $pn->id,
                    'display_number' => $pn->display_number,
                    'name' => $pn->name,
                ]),
            ]);
        } catch (\Exception $e) {
            Log::error('OAuth callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Authentication failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function disconnect(Request $request): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        $success = $this->oauthService->revokeAccess($customer);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Account disconnected successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to disconnect account',
        ], 500);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $agent = $request->user();
        $customer = $agent->customer;

        if ($customer->isTokenExpired()) {
            $newToken = $this->oauthService->refreshLongLivedToken($customer->access_token);

            if ($newToken) {
                $customer->update([
                    'access_token' => $newToken['access_token'],
                    'token_expires_at' => now()->addSeconds($newToken['expires_in']),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Token refreshed successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed. Please reconnect your account.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token is still valid',
            'expires_at' => $customer->token_expires_at,
        ]);
    }
}

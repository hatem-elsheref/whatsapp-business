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

    public function manualSetup(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'app_id' => 'required|string',
                'app_secret' => 'nullable|string',
                'access_token' => 'required|string',
                'phone_number_id' => 'nullable|string',
            ]);

            $agent = $request->user();
            $customer = $agent->customer;

            // Verify the access token works
            $tokenInfo = $this->oauthService->verifyAccessToken($validated['access_token']);

            if (!$tokenInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid access token. Please check and try again.',
                ], 400);
            }

            // Get business info
            $businessInfo = $this->oauthService->getBusinessInfo($validated['access_token']);

            // Update or create customer
            $customer->update([
                'fb_app_id' => $validated['app_id'],
                'fb_app_secret' => $validated['app_secret'] ? encrypt($validated['app_secret']) : null,
                'access_token' => encrypt($validated['access_token']),
                'token_expires_at' => now()->addDays(60),
                'business_name' => $businessInfo['name'] ?? 'WhatsApp Business',
            ]);

            // Sync phone numbers if app_id is provided
            $phoneNumbers = [];
            if ($validated['phone_number_id']) {
                // Sync specific phone number
                $phoneNumber = $this->oauthService->syncSinglePhoneNumber(
                    $customer,
                    $validated['phone_number_id'],
                    $validated['access_token']
                );
                if ($phoneNumber) {
                    $phoneNumbers[] = $phoneNumber;
                }
            } else {
                // Sync all phone numbers
                $phoneNumbers = $this->oauthService->syncPhoneNumbers($customer);
            }

            Log::info('Manual setup completed', [
                'customer_id' => $customer->id,
                'phone_numbers_count' => count($phoneNumbers),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Account configured successfully',
                'phone_numbers' => collect($phoneNumbers)->map(fn($pn) => [
                    'id' => $pn->id,
                    'display_number' => $pn->display_number,
                    'verified_name' => $pn->verified_name,
                    'webhook_verified' => $pn->webhook_verified,
                ]),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Manual setup error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Setup failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}

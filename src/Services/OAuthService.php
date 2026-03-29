<?php

namespace WhatsApp\Business\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use WhatsApp\Business\Models\Customer;
use WhatsApp\Business\Models\PhoneNumber;

class OAuthService
{
    private Client $client;
    private string $appId;
    private string $appSecret;
    private string $authUrl;
    private string $graphUrl;
    private string $apiVersion;

    private const REQUIRED_PERMISSIONS = [
        'whatsapp_business_management',
        'whatsapp_business_messaging',
        'business_management',
    ];

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
        
        $this->appId = config('whatsapp.meta.app_id');
        $this->appSecret = config('whatsapp.meta.app_secret');
        $this->authUrl = config('whatsapp.oauth.auth_url', 'https://www.facebook.com/v18.0/dialog/oauth');
        $this->graphUrl = config('whatsapp.meta.graph_url', 'https://graph.facebook.com');
        $this->apiVersion = config('whatsapp.meta.api_version', 'v18.0');
    }

    public function getAuthorizationUrl(string $redirectUri, ?string $state = null): string
    {
        $params = [
            'client_id' => $this->appId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', self::REQUIRED_PERMISSIONS),
            'response_type' => 'code',
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return $this->authUrl . '?' . http_build_query($params);
    }

    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        try {
            $response = Http::get("{$this->graphUrl}/{$this->apiVersion}/oauth/access_token", [
                'client_id' => $this->appId,
                'client_secret' => $this->appSecret,
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

            if ($response->failed()) {
                Log::error('Token exchange failed', ['response' => $response->json()]);
                throw new \Exception('Failed to exchange code for token');
            }

            return $response->json();
        } catch (GuzzleException $e) {
            Log::error('Token exchange exception', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getLongLivedToken(string $shortLivedToken): array
    {
        try {
            $response = Http::get("{$this->graphUrl}/{$this->apiVersion}/oauth/access_token", [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $this->appId,
                'client_secret' => $this->appSecret,
                'fb_exchange_token' => $shortLivedToken,
            ]);

            if ($response->failed()) {
                throw new \Exception('Failed to get long-lived token');
            }

            return $response->json();
        } catch (GuzzleException $e) {
            Log::error('Long-lived token exchange failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function refreshLongLivedToken(string $accessToken): ?array
    {
        try {
            $response = Http::get("{$this->graphUrl}/{$this->apiVersion}/oauth/access_token", [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $this->appId,
                'client_secret' => $this->appSecret,
                'fb_exchange_token' => $accessToken,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (GuzzleException $e) {
            Log::warning('Token refresh failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function debugToken(string $accessToken): array
    {
        try {
            $response = Http::get("{$this->graphUrl}/{$this->apiVersion}/debug_token", [
                'input_token' => $accessToken,
                'access_token' => "{$this->appId}|{$this->appSecret}",
            ]);

            return $response->successful() ? $response->json() : [];
        } catch (GuzzleException $e) {
            Log::error('Token debug failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getUserInfo(string $accessToken): array
    {
        try {
            $response = Http::get("{$this->graphUrl}/{$this->apiVersion}/me", [
                'access_token' => $accessToken,
                'fields' => 'id,name,email,first_name,last_name,picture',
            ]);

            return $response->successful() ? $response->json() : [];
        } catch (GuzzleException $e) {
            Log::error('Get user info failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getBusinessAccounts(string $accessToken): array
    {
        try {
            $response = Http::get("{$this->graphUrl}/{$this->apiVersion}/me/businesses", [
                'access_token' => $accessToken,
                'fields' => 'id,name,verification_status,primary_page',
            ]);

            if ($response->successful()) {
                return $response->json()['data'] ?? [];
            }

            return [];
        } catch (GuzzleException $e) {
            Log::error('Get business accounts failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getWhatsAppBusinessAccounts(string $accessToken, string $businessId): array
    {
        try {
            $response = Http::get("{$this->graphUrl}/{$this->apiVersion}/{$businessId}/client_whatsapp_business_accounts", [
                'access_token' => $accessToken,
                'fields' => 'id,name,account_review_status,verification_status',
            ]);

            if ($response->successful()) {
                return $response->json()['data'] ?? [];
            }

            return [];
        } catch (GuzzleException $e) {
            Log::error('Get WhatsApp Business accounts failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getWABAPhoneNumbers(string $accessToken, string $wabaId): array
    {
        try {
            $response = Http::get("{$this->graphUrl}/{$this->apiVersion}/{$wabaId}/phone_numbers", [
                'access_token' => $accessToken,
                'fields' => 'id,display_phone_number,verified_name,quality_score,code_verification_status,is_pin_enabled,account_linked_at',
            ]);

            if ($response->successful()) {
                return $response->json()['data'] ?? [];
            }

            return [];
        } catch (GuzzleException $e) {
            Log::error('Get WABA phone numbers failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function createOrUpdateCustomer(array $userData, string $accessToken, string $longLivedToken, int $expiresIn): Customer
    {
        $tokenExpiresAt = now()->addSeconds($expiresIn);

        $customer = Customer::updateOrCreate(
            ['meta_user_id' => $userData['id']],
            [
                'business_name' => $userData['name'] ?? 'Business',
                'business_email' => $userData['email'] ?? null,
                'access_token' => $longLivedToken,
                'token_expires_at' => $tokenExpiresAt,
                'is_active' => true,
            ]
        );

        return $customer;
    }

    public function syncPhoneNumbers(Customer $customer): array
    {
        $syncedPhoneNumbers = [];
        
        $businessAccounts = $this->getBusinessAccounts($customer->access_token);
        
        foreach ($businessAccounts as $business) {
            $wabaAccounts = $this->getWhatsAppBusinessAccounts($customer->access_token, $business['id']);
            
            foreach ($wabaAccounts as $waba) {
                $phoneNumbers = $this->getWABAPhoneNumbers($customer->access_token, $waba['id']);
                
                foreach ($phoneNumbers as $phoneData) {
                    $phoneNumber = PhoneNumber::updateOrCreate(
                        ['phone_number_id' => $phoneData['id']],
                        [
                            'customer_id' => $customer->id,
                            'raw_number' => $phoneData['id'],
                            'display_number' => $phoneData['display_phone_number'],
                            'name' => $phoneData['verified_name'] ?? null,
                            'waba_id' => $waba['id'],
                            'waba_name' => $waba['name'],
                            'quality_score' => $phoneData['quality_score'] ?? null,
                            'status' => 'connected',
                            'is_active' => true,
                        ]
                    );
                    
                    $syncedPhoneNumbers[] = $phoneNumber;
                }
            }
        }

        return $syncedPhoneNumbers;
    }

    public function revokeAccess(Customer $customer): bool
    {
        try {
            $response = Http::delete("{$this->graphUrl}/{$this->apiVersion}/{$customer->meta_user_id}/permissions", [
                'access_token' => $customer->access_token,
            ]);

            if ($response->successful()) {
                $customer->update(['is_active' => false]);
                return true;
            }

            return false;
        } catch (GuzzleException $e) {
            Log::error('Revoke access failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function verifyPermissions(string $accessToken): array
    {
        try {
            $response = Http::get("{$this->graphUrl}/{$this->apiVersion}/me/permissions", [
                'access_token' => $accessToken,
            ]);

            if ($response->successful()) {
                return $response->json()['data'] ?? [];
            }

            return [];
        } catch (GuzzleException $e) {
            Log::error('Verify permissions failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function hasRequiredPermissions(array $permissions): bool
    {
        $grantedPermissions = array_column(
            array_filter($permissions, fn($p) => $p['status'] === 'granted'),
            'permission'
        );

        foreach (self::REQUIRED_PERMISSIONS as $required) {
            if (!in_array($required, $grantedPermissions)) {
                return false;
            }
        }

        return true;
    }

    public function verifyAccessToken(string $accessToken): ?array
    {
        try {
            $response = Http::get("{$this->graphUrl}/{$this->apiVersion}/debug_token", [
                'input_token' => $accessToken,
                'access_token' => "{$this->appId}|{$this->appSecret}",
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data']['is_valid']) && $data['data']['is_valid']) {
                    return $data['data'];
                }
            }

            return null;
        } catch (GuzzleException $e) {
            Log::error('Verify access token failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function getBusinessInfo(string $accessToken): array
    {
        try {
            $response = Http::get("{$this->graphUrl}/{$this->apiVersion}/me", [
                'access_token' => $accessToken,
                'fields' => 'id,name,email',
            ]);

            return $response->successful() ? $response->json() : [];
        } catch (GuzzleException $e) {
            Log::error('Get business info failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function syncSinglePhoneNumber(Customer $customer, string $phoneNumberId, string $accessToken): ?PhoneNumber
    {
        try {
            $response = Http::get("{$this->graphUrl}/{$this->apiVersion}/{$phoneNumberId}", [
                'access_token' => $accessToken,
                'fields' => 'id,display_phone_number,verified_name,quality_score',
            ]);

            if ($response->successful()) {
                $phoneData = $response->json();
                
                return PhoneNumber::updateOrCreate(
                    ['phone_number_id' => $phoneData['id']],
                    [
                        'customer_id' => $customer->id,
                        'raw_number' => $phoneData['id'],
                        'display_number' => $phoneData['display_phone_number'],
                        'name' => $phoneData['verified_name'] ?? null,
                        'quality_score' => $phoneData['quality_score'] ?? null,
                        'status' => 'connected',
                        'is_active' => true,
                    ]
                );
            }

            return null;
        } catch (GuzzleException $e) {
            Log::error('Sync single phone number failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}

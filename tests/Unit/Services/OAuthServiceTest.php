<?php

namespace WhatsApp\Business\Tests\Unit\Services;

use WhatsApp\Business\Tests\TestCase;
use WhatsApp\Business\Services\OAuthService;
use WhatsApp\Business\Models\Customer;

class OAuthServiceTest extends TestCase
{
    protected OAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OAuthService();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(OAuthService::class, $this->service);
    }

    public function test_get_authorization_url_returns_valid_facebook_url(): void
    {
        config([
            'services.whatsapp.app_id' => '123456789',
            'services.whatsapp.redirect_uri' => 'https://example.com/callback',
        ]);

        $url = $this->service->getAuthorizationUrl('test-state');

        $this->assertStringContainsString('facebook.com', $url);
        $this->assertStringContainsString('client_id=123456789', $url);
        $this->assertStringContainsString('redirect_uri=', $url);
    }

    public function test_token_refresh_request_structure(): void
    {
        $this->markTestSkipped('Requires API credentials for integration test');

        $result = $this->service->refreshAccessToken('test-refresh-token');

        $this->assertIsArray($result);
    }
}

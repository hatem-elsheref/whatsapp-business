<?php

namespace WhatsApp\Business\Tests\Unit\Services;

use WhatsApp\Business\Tests\TestCase;
use WhatsApp\Business\Services\WhatsAppCloudService;
use Mockery;

class WhatsAppCloudServiceTest extends TestCase
{
    protected WhatsAppCloudService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WhatsAppCloudService();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(WhatsAppCloudService::class, $this->service);
    }

    public function test_send_text_message_returns_expected_structure(): void
    {
        $this->markTestSkipped('Requires API credentials for integration test');

        $result = $this->service->sendMessage(
            '15551234567',
            'Test message',
            '123456789'
        );

        $this->assertIsArray($result);
    }

    public function test_send_template_message_validates_phone_number(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->sendTemplate(
            '',
            'hello_template',
            'en',
            []
        );
    }
}

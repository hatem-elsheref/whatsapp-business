<?php

namespace WhatsApp\Business\Tests\Unit\Models;

use WhatsApp\Business\Tests\TestCase;
use WhatsApp\Business\Models\Customer;
use WhatsApp\Business\Models\Agent;
use WhatsApp\Business\Models\PhoneNumber;

class CustomerTest extends TestCase
{
    public function test_customer_can_be_created(): void
    {
        $customer = Customer::create([
            'fb_business_id' => '123456789',
            'fb_access_token' => encrypt('test-token'),
            'fb_page_id' => '987654321',
            'wa_business_account_id' => 'WA123',
            'name' => 'Test Business',
            'email' => 'test@example.com',
        ]);

        $this->assertNotNull($customer->id);
        $this->assertEquals('Test Business', $customer->name);
    }

    public function test_customer_encrypts_fb_access_token(): void
    {
        $token = 'secret-fb-token';

        $customer = Customer::create([
            'fb_business_id' => '123456789',
            'fb_access_token' => encrypt($token),
            'fb_page_id' => '987654321',
            'wa_business_account_id' => 'WA123',
            'name' => 'Test Business',
        ]);

        $this->assertNotEquals($token, $customer->fb_access_token);
        $this->assertEquals($token, decrypt($customer->fb_access_token));
    }

    public function test_customer_has_many_agents(): void
    {
        $customer = Customer::create([
            'fb_business_id' => '123456789',
            'fb_access_token' => encrypt('token'),
            'fb_page_id' => '987654321',
            'wa_business_account_id' => 'WA123',
            'name' => 'Test Business',
        ]);

        Agent::create([
            'customer_id' => $customer->id,
            'name' => 'Agent 1',
            'email' => 'agent1@test.com',
            'password' => 'hashed',
        ]);

        $this->assertCount(1, $customer->agents);
    }

    public function test_customer_has_many_phone_numbers(): void
    {
        $customer = Customer::create([
            'fb_business_id' => '123456789',
            'fb_access_token' => encrypt('token'),
            'fb_page_id' => '987654321',
            'wa_business_account_id' => 'WA123',
            'name' => 'Test Business',
        ]);

        PhoneNumber::create([
            'customer_id' => $customer->id,
            'wa_id' => '15551234567',
            'display_phone_number' => '+1 555-123-4567',
            'verified_name' => 'Test Business',
        ]);

        $this->assertCount(1, $customer->phoneNumbers);
    }
}

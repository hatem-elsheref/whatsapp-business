<?php

namespace WhatsApp\Business\Tests\Unit\Models;

use WhatsApp\Business\Tests\TestCase;
use WhatsApp\Business\Models\Agent;
use WhatsApp\Business\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AgentTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::create([
            'fb_business_id' => '123456789',
            'fb_access_token' => encrypt('token'),
            'fb_page_id' => '987654321',
            'wa_business_account_id' => 'WA123',
            'name' => 'Test Business',
        ]);
    }

    public function test_agent_can_be_created(): void
    {
        $agent = Agent::create([
            'customer_id' => $this->customer->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->assertNotNull($agent->id);
        $this->assertEquals('John Doe', $agent->name);
        $this->assertEquals('admin', $agent->role);
    }

    public function test_agent_belongs_to_customer(): void
    {
        $agent = Agent::create([
            'customer_id' => $this->customer->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->assertEquals($this->customer->id, $agent->customer->id);
    }

    public function test_agent_can_have_multiple_roles(): void
    {
        $roles = ['admin', 'agent', 'supervisor'];

        foreach ($roles as $role) {
            Agent::create([
                'customer_id' => $this->customer->id,
                'name' => "Agent $role",
                'email' => "$role@example.com",
                'password' => bcrypt('password'),
                'role' => $role,
            ]);
        }

        $this->assertCount(3, $this->customer->agents);
    }

    public function test_agent_can_be_activated_deactivated(): void
    {
        $agent = Agent::create([
            'customer_id' => $this->customer->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->assertTrue($agent->is_active);

        $agent->update(['is_active' => false]);
        $this->assertFalse($agent->is_active);
    }
}

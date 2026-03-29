<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use WhatsApp\Business\Models\Customer;
use WhatsApp\Business\Models\Agent;

class WhatsAppAdminSeeder extends Seeder
{
    public function run(): void
    {
        $customer = Customer::firstOrCreate(
            ['business_email' => 'admin@whatsapp.local'],
            [
                'business_name' => 'WhatsApp Admin Portal',
                'is_active' => true,
                'is_verified' => true,
            ]
        );

        $admin = Agent::firstOrCreate(
            ['email' => 'admin@whatsapp.local'],
            [
                'customer_id' => $customer->id,
                'name' => 'Admin',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        if ($admin->wasRecentlyCreated) {
            $this->command->info('Admin user created successfully!');
            $this->command->info('Email: admin@whatsapp.local');
            $this->command->info('Password: admin123');
        } else {
            $this->command->info('Admin user already exists.');
        }

        // Create sample agent
        $agent = Agent::firstOrCreate(
            ['email' => 'agent@whatsapp.local'],
            [
                'customer_id' => $customer->id,
                'name' => 'Sample Agent',
                'password' => Hash::make('agent123'),
                'role' => 'agent',
                'is_active' => true,
            ]
        );

        if ($agent->wasRecentlyCreated) {
            $this->command->info('Sample agent created successfully!');
            $this->command->info('Email: agent@whatsapp.local');
            $this->command->info('Password: agent123');
        }
    }
}

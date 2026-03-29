<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use WhatsApp\Business\Models\Customer;
use WhatsApp\Business\Models\Agent;

class WhatsAppBusinessSeeder extends Seeder
{
    public function run(): void
    {
        // Create default customer
        $customer = Customer::create([
            'business_name' => config('whatsapp.seeder.customer_name', 'Default Business'),
            'business_email' => config('whatsapp.seeder.customer_email', 'admin@example.com'),
            'is_active' => true,
            'is_verified' => true,
        ]);

        // Create admin agent
        $admin = Agent::create([
            'customer_id' => $customer->id,
            'name' => config('whatsapp.seeder.admin_name', 'Admin'),
            'email' => config('whatsapp.seeder.admin_email', 'admin@example.com'),
            'password' => Hash::make(config('whatsapp.seeder.admin_password', 'password')),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->command->info('WhatsApp Business seeder completed!');
        $this->command->info('Admin email: ' . config('whatsapp.seeder.admin_email', 'admin@example.com'));
        $this->command->info('Admin password: ' . config('whatsapp.seeder.admin_password', 'password'));
    }
}

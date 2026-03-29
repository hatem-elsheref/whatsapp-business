<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wa_customers')->cascadeOnDelete();
            $table->foreignId('phone_number_id')->constrained('wa_phone_numbers')->cascadeOnDelete();
            $table->string('wa_id')->comment('Customer WhatsApp ID');
            $table->string('customer_name')->nullable();
            $table->string('customer_profile_photo_url')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview')->nullable();
            $table->string('last_message_direction')->nullable()->comment('inbound/outbound');
            $table->enum('status', ['active', 'archived', 'blocked', 'pending'])->default('active');
            $table->timestamp('window_expires_at')->nullable()->comment('24-hour message window');
            $table->unsignedInteger('unread_count')->default(0);
            $table->foreignId('assigned_agent_id')->nullable()->constrained('wa_agents')->nullOnDelete();
            $table->json('metadata')->nullable()->comment('Custom fields, tags, etc');
            $table->json('context')->nullable()->comment('Conversation context data');
            $table->string('source')->nullable()->comment('Where conversation originated');
            $table->timestamps();
            
            $table->index(['customer_id', 'status']);
            $table->index(['phone_number_id', 'status']);
            $table->index(['assigned_agent_id']);
            $table->index(['wa_id', 'phone_number_id']);
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_conversations');
    }
};

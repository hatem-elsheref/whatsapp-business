<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_conversations')) {
            return;
        }

        Schema::create('wa_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wa_customers')->cascadeOnDelete();
            $table->foreignId('phone_number_id')->constrained('wa_phone_numbers')->cascadeOnDelete();
            $table->string('wa_id');
            $table->string('customer_name')->nullable();
            $table->string('customer_profile_photo_url')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview')->nullable();
            $table->string('last_message_direction')->nullable();
            $table->enum('status', ['active', 'archived', 'blocked', 'pending'])->default('active');
            $table->timestamp('window_expires_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->foreignId('assigned_agent_id')->nullable()->constrained('wa_agents')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->json('context')->nullable();
            $table->string('source')->nullable();
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

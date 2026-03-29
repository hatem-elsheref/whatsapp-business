<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wa_customers')->cascadeOnDelete();
            $table->foreignId('phone_number_id')->nullable()->constrained('wa_phone_numbers')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('wa_agents')->nullOnDelete();
            $table->string('event_type');
            $table->json('event_data')->nullable();
            $table->string('wa_id')->nullable()->comment('Customer WhatsApp ID');
            $table->string('conversation_id')->nullable();
            $table->string('message_id')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'event_type', 'occurred_at']);
            $table->index(['phone_number_id', 'occurred_at']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_analytics_events');
    }
};

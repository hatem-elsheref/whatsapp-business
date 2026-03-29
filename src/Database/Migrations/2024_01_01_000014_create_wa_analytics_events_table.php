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
            $table->foreignId('agent_id')->nullable()->constrained('wa_agents')->nullOnDelete();
            $table->string('event_type');
            $table->string('event_category');
            $table->json('event_data')->nullable();
            $table->string('wa_id')->nullable();
            $table->string('phone_number_id')->nullable();
            $table->string('message_id')->nullable();
            $table->string('conversation_id')->nullable();
            $table->timestamp('event_timestamp')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'event_type', 'event_timestamp']);
            $table->index(['agent_id', 'event_timestamp']);
            $table->index('event_timestamp');
        });
    }

    public function down(): void
    {
        Schema::table('wa_analytics_events', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['agent_id']);
        });
        Schema::dropIfExists('wa_analytics_events');
    }
};

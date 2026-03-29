<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_notifications')) {
            return;
        }

        Schema::create('wa_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wa_customers')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('wa_agents')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('wa_conversations')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('wa_tickets')->nullOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->string('action_url')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->index(['agent_id', 'is_read']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_notifications');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wa_customers')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('wa_conversations')->nullOnDelete();
            $table->string('ticket_number')->unique();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['open', 'pending', 'on_hold', 'resolved', 'closed'])->default('open');
            $table->foreignId('assigned_agent_id')->nullable()->constrained('wa_agents')->nullOnDelete();
            $table->foreignId('created_by_agent_id')->nullable()->constrained('wa_agents')->nullOnDelete();
            $table->foreignId('closed_by_agent_id')->nullable()->constrained('wa_agents')->nullOnDelete();
            $table->foreignId('resolved_by_agent_id')->nullable()->constrained('wa_agents')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->integer('response_count')->default(0);
            $table->integer('message_count')->default(0);
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'status']);
            $table->index(['assigned_agent_id', 'status']);
            $table->index('priority');
            $table->index('ticket_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_tickets');
    }
};

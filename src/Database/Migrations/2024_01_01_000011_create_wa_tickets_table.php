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
            $table->string('ticket_number')->unique();
            $table->foreignId('customer_id')->constrained('wa_customers')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('wa_conversations')->nullOnDelete();
            $table->foreignId('created_by_agent_id')->nullable()->constrained('wa_agents')->nullOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('wa_agents')->nullOnDelete();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->enum('status', ['open', 'pending', 'resolved', 'closed'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->json('metadata')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'status']);
            $table->index(['assigned_agent_id', 'status']);
            $table->index('ticket_number');
        });
    }

    public function down(): void
    {
        Schema::table('wa_tickets', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['conversation_id']);
            $table->dropForeign(['created_by_agent_id']);
            $table->dropForeign(['assigned_agent_id']);
        });
        Schema::dropIfExists('wa_tickets');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('wa_tickets')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('wa_agents')->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('wa_messages')->nullOnDelete();
            $table->enum('type', ['note', 'reply', 'system', 'internal'])->default('note');
            $table->text('content');
            $table->boolean('is_internal')->default(false)->comment('Only visible to agents');
            $table->timestamps();
            
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_ticket_messages');
    }
};

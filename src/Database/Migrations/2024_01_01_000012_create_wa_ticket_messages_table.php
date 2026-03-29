<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_ticket_messages')) {
            return;
        }

        Schema::create('wa_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('wa_tickets')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('wa_messages')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('wa_agents')->nullOnDelete();
            $table->enum('type', ['note', 'reply', 'system'])->default('note');
            $table->text('message');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
            
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_ticket_messages');
    }
};

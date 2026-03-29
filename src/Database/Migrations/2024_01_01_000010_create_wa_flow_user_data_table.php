<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_flow_user_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wa_customers')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('wa_conversations')->cascadeOnDelete();
            $table->foreignId('flow_id')->constrained('wa_flows')->cascadeOnDelete();
            $table->json('variables')->comment('Collected data as key-value pairs');
            $table->integer('current_step')->default(0);
            $table->string('current_step_id')->nullable();
            $table->enum('status', ['active', 'completed', 'abandoned', 'timeout'])->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['conversation_id', 'flow_id']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_flow_user_data');
    }
};

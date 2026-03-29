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
            $table->foreignId('flow_id')->constrained('wa_flows')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('wa_conversations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('wa_customers')->cascadeOnDelete();
            $table->string('wa_id');
            $table->foreignId('current_step_id')->nullable()->constrained('wa_flow_steps')->nullOnDelete();
            $table->enum('status', ['active', 'completed', 'abandoned', 'expired'])->default('active');
            $table->json('variables')->nullable();
            $table->text('current_response')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index(['flow_id', 'status']);
            $table->index(['conversation_id']);
            $table->index('wa_id');
        });
    }

    public function down(): void
    {
        Schema::table('wa_flow_user_data', function (Blueprint $table) {
            $table->dropForeign(['flow_id']);
            $table->dropForeign(['conversation_id']);
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['current_step_id']);
        });
        Schema::dropIfExists('wa_flow_user_data');
    }
};

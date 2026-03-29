<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_flow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('wa_flows')->cascadeOnDelete();
            $table->integer('step_order');
            $table->string('step_id')->unique()->comment('Internal step identifier');
            $table->enum('step_type', ['message', 'question', 'condition', 'action', 'delay', 'end', 'api_call', 'ticket'])->default('message');
            $table->json('content')->nullable()->comment('Message body, buttons, etc');
            $table->foreignId('next_step_id')->nullable()->constrained('wa_flow_steps')->nullOnDelete();
            $table->json('branches')->nullable()->comment('For conditional steps');
            $table->integer('step_timeout_seconds')->default(0)->comment('Wait for response, 0 = no wait');
            $table->string('collected_variable')->nullable()->comment('Store response as variable');
            $table->string('variable_type')->nullable()->comment('Type: text, number, email, phone');
            $table->json('validation_rules')->nullable();
            $table->json('actions')->nullable()->comment('API calls, ticket creation, etc');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['flow_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_flow_steps');
    }
};

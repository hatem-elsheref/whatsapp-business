<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wa_customers')->cascadeOnDelete();
            $table->foreignId('phone_number_id')->nullable()->constrained('wa_phone_numbers')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('trigger_type', ['keyword', 'new_conversation', 'button_click', 'flow_completion', 'scheduled', 'api'])->default('keyword');
            $table->string('trigger_value')->nullable()->comment('Keyword or trigger config');
            $table->boolean('is_active')->default(false);
            $table->boolean('allow_user_interruption')->default(true);
            $table->integer('max_steps')->default(50);
            $table->integer('timeout_minutes')->default(30);
            $table->json('settings')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'is_active']);
            $table->index(['trigger_type', 'trigger_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_flows');
    }
};

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
            $table->string('name');
            $table->enum('step_type', ['message', 'question', 'condition', 'action', 'delay', 'end'])->default('message');
            $table->integer('order')->default(0);
            $table->json('config')->nullable();
            $table->json('buttons')->nullable();
            $table->json('conditions')->nullable();
            $table->json('actions')->nullable();
            $table->integer('timeout_seconds')->nullable();
            $table->string('timeout_action')->nullable();
            $table->boolean('is_entry_point')->default(false);
            $table->timestamps();
            
            $table->index('flow_id');
        });
    }

    public function down(): void
    {
        Schema::table('wa_flow_steps', function (Blueprint $table) {
            $table->dropForeign(['flow_id']);
        });
        Schema::dropIfExists('wa_flow_steps');
    }
};

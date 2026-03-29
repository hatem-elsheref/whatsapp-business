<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_quick_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('wa_customers')->cascadeOnDelete();
            $table->string('text', 512);
            $table->string('shortcut')->nullable()->comment('Trigger keyword');
            $table->string('category')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_global')->default(false)->comment('Available to all phone numbers');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_agent_id')->nullable()->constrained('wa_agents')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['customer_id', 'is_active', 'sort_order']);
            $table->index('is_global');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_quick_replies');
    }
};

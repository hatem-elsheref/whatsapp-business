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
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('trigger_type', ['keyword', 'menu', 'button', 'webhook', 'auto'])->default('keyword');
            $table->string('trigger_value')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('settings')->nullable();
            $table->integer('total_starts')->default(0);
            $table->integer('total_completions')->default(0);
            $table->integer('total_abandons')->default(0);
            $table->timestamps();
            
            $table->index(['customer_id', 'is_active']);
            $table->index('trigger_type');
        });
    }

    public function down(): void
    {
        Schema::table('wa_flows', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
        Schema::dropIfExists('wa_flows');
    }
};

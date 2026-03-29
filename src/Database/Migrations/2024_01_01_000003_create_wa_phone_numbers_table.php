<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_phone_numbers')) {
            return;
        }

        Schema::create('wa_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wa_customers')->cascadeOnDelete();
            $table->string('phone_number_id')->unique()->nullable();
            $table->string('raw_number');
            $table->string('display_number');
            $table->string('verified_name')->nullable();
            $table->string('name')->nullable();
            $table->string('waba_id')->nullable();
            $table->string('waba_name')->nullable();
            $table->string('quality_score')->nullable();
            $table->enum('status', ['connected', 'disconnected', 'pending'])->default('connected');
            $table->boolean('webhook_verified')->default(false);
            $table->string('webhook_url')->nullable();
            $table->string('webhook_verify_token')->nullable();
            $table->json('capabilities')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['customer_id', 'is_active']);
            $table->index('waba_id');
            $table->index('raw_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_phone_numbers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_agents')) {
            return;
        }

        Schema::create('wa_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wa_customers')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('avatar_url')->nullable();
            $table->enum('role', ['admin', 'supervisor', 'agent'])->default('agent');
            $table->boolean('is_active')->default(true);
            $table->string('pusher_channel')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            
            $table->index(['customer_id', 'email']);
            $table->index(['customer_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_agents');
    }
};

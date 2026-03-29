<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wa_customers')->cascadeOnDelete();
            $table->string('template_id')->unique()->nullable();
            $table->string('name');
            $table->string('language')->default('ar');
            $table->enum('category', ['MARKETING', 'UTILITY', 'AUTHENTICATION'])->default('UTILITY');
            $table->enum('status', ['APPROVED', 'REJECTED', 'PENDING', 'PAUSED', 'DELETED'])->default('PENDING');
            $table->json('components')->nullable();
            $table->json('example')->nullable();
            $table->string('meta_category')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('allow_category_change')->default(true);
            $table->timestamps();
            
            $table->index(['customer_id', 'status']);
            $table->index(['name', 'language']);
        });
    }

    public function down(): void
    {
        Schema::table('wa_templates', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });
        Schema::dropIfExists('wa_templates');
    }
};

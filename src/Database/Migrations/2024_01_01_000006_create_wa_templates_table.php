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
            $table->foreignId('phone_number_id')->nullable()->constrained('wa_phone_numbers')->nullOnDelete();
            $table->string('meta_template_id')->nullable();
            $table->string('name')->comment('Template name (e.g., hello_world)');
            $table->string('display_name')->nullable();
            $table->enum('category', ['marketing', 'utility', 'authentication'])->default('utility');
            $table->string('language')->default('en');
            $table->enum('status', ['pending', 'approved', 'rejected', 'deprecated', 'paused'])->default('pending');
            $table->json('components')->comment('Full template structure from Meta');
            $table->json('example_data')->nullable()->comment('Sample variable data');
            $table->json('variable_mappings')->nullable()->comment('Variable to data source mappings');
            $table->boolean('allow_category_change')->default(false);
            $table->integer('daily_limit')->nullable();
            $table->integer('monthly_usage')->default(0);
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'status']);
            $table->index('name');
            $table->index(['category', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_templates');
    }
};

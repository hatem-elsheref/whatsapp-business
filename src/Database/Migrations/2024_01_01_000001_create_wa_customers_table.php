<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_customers')) {
            return;
        }

        Schema::create('wa_customers', function (Blueprint $table) {
            $table->id();
            $table->string('meta_user_id')->unique()->nullable();
            $table->string('business_name');
            $table->string('business_email')->nullable();
            $table->string('fb_app_id')->nullable();
            $table->text('fb_app_secret')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('fb_business_id')->nullable();
            $table->string('fb_page_id')->nullable();
            $table->string('wa_business_account_id')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            
            $table->index('fb_business_id');
            $table->index('business_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_customers');
    }
};

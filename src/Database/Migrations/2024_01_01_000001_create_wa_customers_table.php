<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_customers', function (Blueprint $table) {
            $table->id();
            $table->string('meta_user_id')->unique()->comment('Facebook User ID from OAuth');
            $table->string('business_name');
            $table->string('business_email')->nullable();
            $table->text('access_token')->nullable()->comment('Long-lived Meta access token (encrypted)');
            $table->timestamp('token_expires_at')->nullable();
            $table->text('refresh_token')->nullable()->comment('Encrypted refresh token');
            $table->string('meta_business_id')->nullable()->comment('Business Manager ID');
            $table->string('meta_business_name')->nullable();
            $table->json('settings')->nullable();
            $table->json('permissions')->nullable()->comment('Granted OAuth permissions');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            
            $table->index('meta_business_id');
            $table->index('business_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_customers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wa_customers')->cascadeOnDelete();
            $table->foreignId('phone_number_id')->constrained('wa_phone_numbers')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('wa_conversations')->cascadeOnDelete();
            $table->string('meta_message_id')->unique()->nullable()->comment('Meta message ID');
            $table->string('message_id')->nullable()->comment('Client message ID for deduplication');
            $table->enum('direction', ['inbound', 'outbound']);
            $table->enum('type', ['text', 'image', 'video', 'audio', 'document', 'sticker', 'location', 'contact', 'template', 'flow', 'interactive', 'unknown'])->default('text');
            $table->text('body')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_mime_type')->nullable();
            $table->string('media_caption')->nullable();
            $table->string('media_sha256')->nullable();
            $table->unsignedBigInteger('media_size')->nullable();
            $table->string('sticker_id')->nullable();
            $table->json('location')->nullable()->comment('Latitude, longitude, name');
            $table->json('contact')->nullable()->comment('Contact info');
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed', 'failed_temporary'])->default('pending');
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->boolean('is_template_reply')->default(false)->comment('Sent within 24h window');
            $table->foreignId('template_id')->nullable()->constrained('wa_templates')->nullOnDelete();
            $table->foreignId('quick_reply_id')->nullable();
            $table->foreignId('flow_step_id')->nullable();
            $table->foreignId('sent_by_agent_id')->nullable()->constrained('wa_agents')->nullOnDelete();
            $table->json('buttons')->nullable()->comment('Interactive buttons response');
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            
            $table->index(['conversation_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['direction', 'status']);
            $table->index('meta_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_messages');
    }
};

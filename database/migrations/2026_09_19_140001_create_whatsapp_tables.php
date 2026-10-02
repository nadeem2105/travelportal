<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp Cloud API inbox: one conversation per customer WhatsApp number,
 * with a message log in both directions. Ties into CRM contacts. The 24-hour
 * customer service window is tracked so the UI knows when only templates are
 * allowed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('wa_id', 32)->unique();      // customer phone in WhatsApp format (E.164 digits)
            $table->string('profile_name')->nullable(); // WhatsApp display name
            $table->timestamp('last_message_at')->nullable();
            $table->text('last_message_preview')->nullable();
            $table->string('last_message_direction', 10)->nullable(); // inbound|outbound
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('window_expires_at')->nullable(); // 24h service window end
            $table->string('status', 20)->default('open'); // open|closed
            $table->timestamps();

            $table->index('last_message_at');
            $table->index('status');
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('wa_message_id')->nullable()->unique(); // Meta message id (idempotency)
            $table->string('direction', 10);   // inbound|outbound
            $table->string('type', 20)->default('text'); // text|image|document|template|...
            $table->text('body')->nullable();
            $table->json('media')->nullable();  // {id,link,mime,filename,caption}
            $table->string('template_name')->nullable();
            $table->string('status', 20)->default('sent'); // sent|delivered|read|failed|received
            $table->text('error')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('admins')->nullOnDelete(); // admin author for outbound
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index('direction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
    }
};

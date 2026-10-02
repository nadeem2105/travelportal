<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keyword auto-reply rules for the WhatsApp chatbot. Inbound messages are
 * matched against active rules (by priority); a matching rule sends a text or
 * template reply. A rule may be a default fallback (no keywords) and/or trigger
 * human handoff (pauses the bot for that conversation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_auto_replies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('match_type', 20)->default('contains'); // exact|contains|starts_with
            $table->json('keywords')->nullable();                  // ["hi","hello"] — null for default fallback
            $table->string('reply_type', 12)->default('text');     // text|template
            $table->text('reply_text')->nullable();
            $table->string('template_name')->nullable();
            $table->string('template_language', 12)->default('en_US');
            $table->boolean('is_default')->default(false);         // fallback when nothing else matches
            $table->boolean('is_handoff')->default(false);         // pause bot + flag for a human
            $table->boolean('active')->default(true);
            $table->unsignedInteger('priority')->default(100);     // lower = evaluated first
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['active', 'priority']);
        });

        // Bot control per conversation: once handed off to a human, stop auto-replying.
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->boolean('bot_paused')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropColumn('bot_paused');
        });
        Schema::dropIfExists('whatsapp_auto_replies');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds AI-assistant identity-verification + conversation-memory columns to
 * whatsapp_conversations. Additive and idempotent: existing WhatsApp inbox,
 * auto-reply and campaign behaviour is untouched. Verification is enforced in
 * the backend (CustomerResolver) — these columns only cache the resolved,
 * verified identity so the assistant does not re-ask on every message.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_conversations')) {
            return;
        }

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_conversations', 'verified_user_id')) {
                $table->unsignedBigInteger('verified_user_id')->nullable()->after('contact_id');
            }
            if (! Schema::hasColumn('whatsapp_conversations', 'verified_contact_id')) {
                $table->unsignedBigInteger('verified_contact_id')->nullable()->after('verified_user_id');
            }
            if (! Schema::hasColumn('whatsapp_conversations', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verified_contact_id');
            }
            // Short-lived conversational memory for the AI assistant: active
            // booking context, last intent, pending confirmation, etc. JSON so
            // the shape can evolve without further migrations.
            if (! Schema::hasColumn('whatsapp_conversations', 'assistant_context')) {
                $table->json('assistant_context')->nullable()->after('bot_paused');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('whatsapp_conversations')) {
            return;
        }

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            foreach (['verified_user_id', 'verified_contact_id', 'verified_at', 'assistant_context'] as $col) {
                if (Schema::hasColumn('whatsapp_conversations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

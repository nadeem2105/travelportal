<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB-backed credential store for integrations (WhatsApp Cloud API + AI providers),
 * so admins can manage keys/tokens/template IDs from the panel instead of .env.
 * Secrets live in the `credentials` column, which is encrypted at rest via the
 * model's `encrypted:array` cast. Mirrors the marketing_provider_settings design
 * but is a separate, clearly-scoped table so the two domains stay decoupled.
 *
 * One row per provider slug: 'whatsapp', 'openai', 'anthropic', 'gemini', 'ai'.
 * .env/config remains the fallback — a blank/absent row changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('integration_settings')) {
            return;
        }

        Schema::create('integration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();     // whatsapp | openai | anthropic | gemini | ai
            $table->boolean('enabled')->default(false);
            $table->text('credentials')->nullable();   // encrypted:array (secrets + non-secret config)
            $table->json('meta')->nullable();          // non-secret labels / last test result
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable();   // ok | failed
            $table->text('last_test_message')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
    }
};

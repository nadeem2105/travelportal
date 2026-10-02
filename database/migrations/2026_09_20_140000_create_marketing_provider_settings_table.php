<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores per-provider OAuth *app* credentials (client id/secret, developer token,
 * app id/secret, redirect uri, api version, webhook verify token) in the DB so
 * they no longer have to live in .env. The `credentials` column is encrypted at
 * the model layer (encrypted:array cast) — secrets are never stored in plain text.
 *
 * This is distinct from `marketing_connections`, which stores the per-connection
 * OAuth tokens obtained after the user authorizes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketing_provider_settings')) {
            return;
        }

        Schema::create('marketing_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique(); // google_ads | meta_ads
            $table->boolean('enabled')->default(false);
            $table->text('credentials')->nullable(); // encrypted array of app credentials
            $table->json('meta')->nullable();         // non-secret info (labels, last test result)
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable(); // ok | failed
            $table->text('last_test_message')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_provider_settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedicated password-reset token store for the `agent` auth broker
 * (mirrors the framework's default `password_reset_tokens` table).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agent_password_reset_tokens')) {
            Schema::create('agent_password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_password_reset_tokens');
    }
};

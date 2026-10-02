<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local cache of WhatsApp message templates synced from the Meta WhatsApp
 * Business account. The source of truth is Meta; this table mirrors approved
 * templates so campaigns/auto-replies can pick from them without a live API
 * call on every page load.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('meta_id')->nullable();      // Meta template id
            $table->string('name');                      // template name
            $table->string('language', 12)->default('en_US');
            $table->string('category', 40)->nullable();  // MARKETING|UTILITY|AUTHENTICATION
            $table->string('status', 20)->default('APPROVED'); // APPROVED|PENDING|REJECTED|...
            $table->json('components')->nullable();      // raw components array from Meta
            $table->unsignedSmallInteger('body_variable_count')->default(0);
            $table->text('body_preview')->nullable();    // BODY text with {{n}} placeholders
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['name', 'language']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_auto_replies', function (Blueprint $table) {
            // Ordered body-variable values for a Meta template reply ({{1}}, {{2}}, ...).
            $table->json('template_params')->nullable()->after('template_language');
            // Public HTTPS link for a template's media header (document / image / video).
            $table->string('template_header_media_url', 1000)->nullable()->after('template_params');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_auto_replies', function (Blueprint $table) {
            $table->dropColumn(['template_params', 'template_header_media_url']);
        });
    }
};

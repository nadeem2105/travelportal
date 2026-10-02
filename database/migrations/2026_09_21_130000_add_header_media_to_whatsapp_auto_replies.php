<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_auto_replies', function (Blueprint $table) {
            $table->string('header_type', 20)->default('text')->after('reply_text');
            $table->string('header_image_url', 1000)->nullable()->after('header_text');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_auto_replies', function (Blueprint $table) {
            $table->dropColumn(['header_type', 'header_image_url']);
        });
    }
};

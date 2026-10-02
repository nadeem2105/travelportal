<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_auto_replies', function (Blueprint $table) {
            $table->string('header_text', 60)->nullable()->after('reply_text');
            $table->string('footer_text', 60)->nullable()->after('header_text');
            $table->json('buttons')->nullable()->after('footer_text');
            $table->string('list_button_text', 20)->nullable()->after('buttons');
            $table->json('sections')->nullable()->after('list_button_text');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_auto_replies', function (Blueprint $table) {
            $table->dropColumn(['header_text', 'footer_text', 'buttons', 'list_button_text', 'sections']);
        });
    }
};

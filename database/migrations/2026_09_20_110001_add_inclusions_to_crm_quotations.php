<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inclusions / exclusions on a quotation, so the quote spells out what is and
 * isn't covered. Stored as JSON arrays (one bullet per item).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_quotations', 'inclusions')) {
                $table->json('inclusions')->nullable()->after('cancellation_policy');
            }
            if (! Schema::hasColumn('crm_quotations', 'exclusions')) {
                $table->json('exclusions')->nullable()->after('inclusions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            $table->dropColumn(['inclusions', 'exclusions']);
        });
    }
};

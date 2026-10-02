<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A quotation now carries its own optional travel start (arrival) date so that
 * converting it to a booking anchors real check-in/check-out and pickup dates,
 * instead of relying only on the lead's travel_start_date (often empty).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('crm_quotations', 'travel_date')) {
            Schema::table('crm_quotations', function (Blueprint $table) {
                $table->date('travel_date')->nullable()->after('valid_until');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_quotations', 'travel_date')) {
            Schema::table('crm_quotations', function (Blueprint $table) {
                $table->dropColumn('travel_date');
            });
        }
    }
};

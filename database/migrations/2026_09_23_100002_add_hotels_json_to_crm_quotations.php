<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multiple hotel stays per quotation — a multi-night, multi-location trip
 * (e.g. Srinagar → Gulmarg → Pahalgam) needs one hotel per stop. Stored as a
 * JSON array of { hotel_id, hotel_name, city, location, nights }. The legacy
 * single `hotel_id` column is kept for back-compat (points at the first stay).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_quotations', 'hotels')) {
                $table->json('hotels')->nullable()->after('hotel_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            if (Schema::hasColumn('crm_quotations', 'hotels')) {
                $table->dropColumn('hotels');
            }
        });
    }
};

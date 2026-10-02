<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Day-by-day itinerary on a quotation, with the overnight stay noted per day
 * (e.g. "Day 1 — Arrival in Srinagar · Overnight: Houseboat, Dal Lake").
 * Stored as a JSON array of { day, title, description, stay }. When empty the
 * quote falls back to the linked package's itinerary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_quotations', 'itinerary')) {
                $table->json('itinerary')->nullable()->after('hotels');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            if (Schema::hasColumn('crm_quotations', 'itinerary')) {
                $table->dropColumn('itinerary');
            }
        });
    }
};

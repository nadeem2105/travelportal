<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds MakeMyTrip-style flight configuration to packages.
 *
 * flight_mode:
 *   none      -> no flight step; package is land-only (existing behaviour)
 *   optional  -> "With / Without Flights" — customer may add flights or skip
 *   required  -> fly-in package; a flight option must be chosen
 *
 * Additive and reversible; existing rows default to 'none'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->enum('flight_mode', ['none', 'optional', 'required'])
                ->default('none')
                ->after('hotel_mode');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('flight_mode');
        });
    }
};

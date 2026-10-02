<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds hotel-selection configuration to packages.
 *
 * hotel_mode:
 *   none      -> no hotel selection step (existing packages default here, behave exactly as before)
 *   optional  -> hotel step shown; customer may skip (land-only allowed)
 *   required  -> hotel step shown; at least the included hotel must be chosen
 *
 * This is purely additive and reversible; existing rows default to 'none'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->enum('hotel_mode', ['none', 'optional', 'required'])
                ->default('none')
                ->after('package_type');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('hotel_mode');
        });
    }
};

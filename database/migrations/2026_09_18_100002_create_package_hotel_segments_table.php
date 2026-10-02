<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A "segment" is a leg of a package's stay — e.g. "Srinagar (Day 1-2)",
 * "Gulmarg (Day 3)". Hotel options are grouped under a segment so a package
 * can assign different hotels per destination/night (itinerary-based hotels).
 *
 * A package with a single stay simply has one segment. Packages that never
 * use hotels (hotel_mode = none) will have no segments at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_hotel_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('label');                       // "Srinagar Stay", "Gulmarg Night"
            $table->string('city')->nullable();
            $table->unsignedTinyInteger('day_from')->nullable();
            $table->unsignedTinyInteger('day_to')->nullable();
            $table->unsignedTinyInteger('nights')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['package_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_hotel_segments');
    }
};

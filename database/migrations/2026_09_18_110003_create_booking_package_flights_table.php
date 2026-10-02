<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable snapshot of the flight a customer added to a package booking.
 * Kept separate from `flight_bookings` (the standalone flight product) so the
 * two flows never interfere. Everything documents need is copied at booking
 * time, so later edits to the flight option never alter a historical booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_package_flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            // Soft references
            $table->unsignedBigInteger('package_id')->nullable();
            $table->unsignedBigInteger('package_flight_option_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('supplier_fare_id')->nullable();
            $table->string('supplier_booking_id')->nullable(); // PNR when API-issued

            // Snapshot
            $table->string('label_snapshot')->nullable();
            $table->string('airline_snapshot')->nullable();
            $table->string('origin_city')->nullable();
            $table->string('origin_airport_code', 8)->nullable();
            $table->string('destination_airport_code', 8)->nullable();
            $table->string('trip_type', 20)->default('round_trip');
            $table->string('cabin_class', 30)->default('economy');
            $table->string('baggage_snapshot')->nullable();

            // Occupancy + price
            $table->unsignedTinyInteger('travellers')->default(1);
            $table->string('price_basis', 20)->default('per_person');
            $table->decimal('price_per_person', 10, 2)->default(0);
            $table->decimal('price', 10, 2)->default(0);   // resolved total for this flight line
            $table->decimal('tax', 10, 2)->default(0);

            // Policy
            $table->boolean('refundable')->default(false);
            $table->text('cancellation_policy_snapshot')->nullable();

            $table->string('status', 30)->default('pending'); // pending|confirmed|failed|cancelled
            $table->timestamps();

            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_package_flights');
    }
};

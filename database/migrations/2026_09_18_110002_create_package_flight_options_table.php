<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Selectable flight options offered with a package (e.g. "Ex-Delhi Round Trip").
 *
 * Supports both sourcing modes:
 *   - configured : admin sets origin/airline/price directly
 *   - api        : links to a flight supplier + fare code (supplier_id / supplier_fare_code)
 *
 * `price` is the add-on fare (per person or per booking). "Without flights" is
 * simply the absence of a selection (₹0) — there is no zero-priced row needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_flight_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();

            $table->string('label')->nullable();                 // "Ex-Delhi Round Trip"
            $table->string('origin_city')->nullable();
            $table->string('origin_airport_code', 8)->nullable();
            $table->string('destination_airport_code', 8)->nullable();
            $table->string('airline')->nullable();
            $table->string('airline_code', 8)->nullable();
            $table->enum('trip_type', ['one_way', 'round_trip'])->default('round_trip');
            $table->string('cabin_class', 30)->default('economy'); // economy|premium_economy|business
            $table->string('baggage')->nullable();                 // "15kg check-in + 7kg cabin"

            // API sourcing (optional)
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_fare_code')->nullable();

            // Pricing (add-on, never a delta from base)
            $table->enum('price_basis', ['per_person', 'per_booking'])->default('per_person');
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_default')->default(false);         // recommended option

            // Policy
            $table->boolean('refundable')->default(false);
            $table->text('cancellation_policy')->nullable();

            // Availability window
            $table->date('available_from')->nullable();
            $table->date('available_to')->nullable();

            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['package_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_flight_options');
    }
};

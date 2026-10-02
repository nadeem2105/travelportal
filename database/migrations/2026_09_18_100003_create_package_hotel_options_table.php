<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Selectable hotel options offered for a package (per segment).
 *
 * Supports both sourcing modes:
 *   - manual : links to local hotels / hotel_rooms (hotel_id / hotel_room_id)
 *   - api    : links to a supplier + supplier codes (supplier_id / supplier_hotel_code / supplier_room_code)
 *
 * One option per segment is flagged is_default (the "Included in Package" hotel,
 * upgrade_price = 0). Others are upgrades with a price delta over the included one.
 * Pricing is driven server-side; upgrade_price/extra_* are deltas, never the total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_hotel_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained('package_hotel_segments')->cascadeOnDelete();

            // Source: manual (local) hotel/room
            $table->unsignedBigInteger('hotel_id')->nullable();       // -> hotels.id (soft link; hotels may be deleted)
            $table->unsignedBigInteger('hotel_room_id')->nullable();  // -> hotel_rooms.id

            // Source: API supplier
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_hotel_code')->nullable();
            $table->string('supplier_room_code')->nullable();

            // Display / descriptive
            $table->string('label')->nullable();                      // optional override label
            $table->string('room_type')->nullable();
            $table->string('meal_plan', 50)->default('room_only');    // room_only|breakfast|half_board|full_board
            $table->unsignedTinyInteger('star_rating')->nullable();

            // Occupancy model
            $table->unsignedTinyInteger('base_adults')->default(2);   // occupancy covered by the base/upgrade price
            $table->unsignedTinyInteger('max_adults')->default(3);
            $table->unsignedTinyInteger('max_children')->default(2);
            $table->boolean('extra_bed_allowed')->default(false);

            // Pricing deltas (never totals — the base package price already covers the included option)
            $table->boolean('is_default')->default(false);            // the "Included in Package" option
            $table->enum('price_basis', ['per_person', 'per_room', 'per_booking'])->default('per_booking');
            $table->decimal('upgrade_price', 10, 2)->default(0);      // delta vs the included option
            $table->decimal('extra_adult_price', 10, 2)->nullable();
            $table->decimal('extra_child_price', 10, 2)->nullable();
            $table->decimal('extra_bed_price', 10, 2)->nullable();

            // Policy
            $table->boolean('refundable')->default(true);
            $table->text('cancellation_policy')->nullable();

            // Availability window
            $table->date('available_from')->nullable();
            $table->date('available_to')->nullable();

            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['package_id', 'segment_id', 'status']);
            $table->index('hotel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_hotel_options');
    }
};

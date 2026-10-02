<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable snapshot of the hotel(s) a customer actually booked as part of a
 * package. One row per booked segment/hotel. Everything the invoice, voucher
 * and itinerary need is copied here at booking time, so later edits to the
 * hotel master data NEVER change a historical booking.
 *
 * This is separate from `hotel_bookings` (which serves the standalone hotel
 * product) so neither flow interferes with the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_hotels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            // Soft references (nullable — the snapshot is the source of truth)
            $table->unsignedBigInteger('package_id')->nullable();
            $table->unsignedBigInteger('package_hotel_option_id')->nullable();
            $table->unsignedBigInteger('hotel_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('supplier_hotel_id')->nullable();
            $table->string('supplier_room_id')->nullable();
            $table->string('supplier_booking_id')->nullable();

            // Snapshot (source of truth for documents)
            $table->string('segment_label')->nullable();
            $table->string('hotel_name_snapshot');
            $table->string('address_snapshot')->nullable();
            $table->unsignedTinyInteger('star_rating_snapshot')->nullable();
            $table->string('room_name_snapshot')->nullable();
            $table->string('meal_plan')->nullable();

            $table->date('check_in')->nullable();
            $table->date('check_out')->nullable();
            $table->unsignedTinyInteger('nights')->default(1);
            $table->unsignedTinyInteger('rooms')->default(1);
            $table->unsignedTinyInteger('adults')->default(0);
            $table->unsignedTinyInteger('children')->default(0);
            $table->unsignedTinyInteger('infants')->default(0);
            $table->json('occupancy_snapshot')->nullable();   // bed type, extra bed, child ages, per-room split

            // Price snapshot (deltas + resolved amounts for this hotel line)
            $table->boolean('is_included')->default(false);
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('upgrade_price', 10, 2)->default(0);
            $table->decimal('extra_guest_price', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            // Policy snapshot
            $table->boolean('refundable')->default(true);
            $table->text('cancellation_policy_snapshot')->nullable();

            $table->string('status', 30)->default('pending'); // pending|confirmed|failed|cancelled
            $table->timestamps();

            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_hotels');
    }
};

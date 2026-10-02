<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_reference', 30)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('product_type', ['flight', 'hotel', 'cab', 'package']);
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_booking_id')->nullable();
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->enum('status', ['pending', 'payment_pending', 'confirmed', 'failed', 'cancelled', 'refund_initiated', 'refunded', 'completed', 'payment_success_booking_failed'])->default('pending');
            $table->enum('cancellation_status', ['none', 'requested', 'approved', 'rejected', 'cancelled'])->default('none');
            $table->enum('refund_status', ['none', 'requested', 'initiated', 'processed', 'rejected'])->default('none');
            $table->decimal('supplier_cost', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('markup_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('INR');
            $table->json('contact')->nullable();
            $table->json('price_breakdown')->nullable();
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['product_type', 'status']);
        });

        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 30);
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('name');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->json('details')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_travellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->enum('traveller_type', ['adult', 'child', 'infant'])->default('adult');
            $table->string('title', 10)->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->date('dob')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('nationality', 60)->default('Indian');
            $table->string('id_type', 30)->nullable();
            $table->string('id_number', 60)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('flight_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('pnr', 20)->nullable();
            $table->string('airline_code', 3)->nullable();
            $table->string('flight_number', 10)->nullable();
            $table->json('journey'); // segments, times, cabin
            $table->json('traveller_details')->nullable();
            $table->json('fare_details')->nullable();
            $table->json('seat_selection')->nullable();
            $table->json('baggage_selection')->nullable();
            $table->json('fare_rules')->nullable();
            $table->json('ticket_data')->nullable();
            $table->string('ticket_number')->nullable();
            $table->enum('trip_type', ['one_way', 'round_trip', 'multi_city'])->default('one_way');
            $table->timestamps();
        });

        Schema::create('hotel_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('hotel_id')->nullable();
            $table->string('hotel_name')->nullable();
            $table->string('room_type')->nullable();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedInteger('nights');
            $table->unsignedInteger('rooms')->default(1);
            $table->json('guests')->nullable();
            $table->string('meal_plan', 50)->default('room_only');
            $table->string('supplier_booking_id')->nullable();
            $table->json('voucher_data')->nullable();
            $table->timestamps();
        });

        Schema::create('cab_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->string('vehicle_name')->nullable();
            $table->string('pickup_location');
            $table->string('drop_location')->nullable();
            $table->dateTime('pickup_datetime');
            $table->enum('trip_type', ['one_way', 'round_trip', 'local_rental', 'airport_transfer'])->default('one_way');
            $table->decimal('distance_km', 8, 1)->default(0);
            $table->json('fare_breakdown')->nullable();
            $table->json('driver_details')->nullable();
            $table->timestamps();
        });

        Schema::create('package_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('package_id')->nullable();
            $table->string('package_name')->nullable();
            $table->date('departure_date')->nullable();
            $table->unsignedInteger('adults')->default(2);
            $table->unsignedInteger('children')->default(0);
            $table->unsignedInteger('room_count')->default(1);
            $table->json('price_breakdown')->nullable();
            $table->json('voucher_data')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique(); // razorpay|mock
            $table->string('name');
            $table->boolean('is_enabled')->default(false);
            $table->enum('mode', ['test', 'live'])->default('test');
            $table->text('config'); // encrypted JSON (keys, secrets)
            $table->string('currency', 3)->default('INR');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 30)->default('razorpay');
            $table->string('gateway_order_id')->nullable()->index();
            $table->string('gateway_payment_id')->nullable()->index();
            $table->string('gateway_signature')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->enum('status', ['created', 'authorized', 'captured', 'failed', 'refunded', 'partially_refunded'])->default('created');
            $table->string('method', 40)->nullable();
            $table->json('payload')->nullable();
            $table->json('webhook_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway_refund_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('penalty_amount', 12, 2)->default(0);
            $table->text('reason')->nullable();
            $table->enum('status', ['requested', 'initiated', 'processed', 'rejected', 'failed'])->default('requested');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_gateways');
        Schema::dropIfExists('package_bookings');
        Schema::dropIfExists('cab_bookings');
        Schema::dropIfExists('hotel_bookings');
        Schema::dropIfExists('flight_bookings');
        Schema::dropIfExists('booking_travellers');
        Schema::dropIfExists('booking_items');
        Schema::dropIfExists('bookings');
    }
};

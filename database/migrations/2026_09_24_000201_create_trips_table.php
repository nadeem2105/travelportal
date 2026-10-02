<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trips')) {
            return;
        }

        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            // Authoritative link — the booking remains the single source of truth.
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->string('trip_reference')->unique();
            $table->string('status', 32)->default('upcoming')->index(); // upcoming|arriving_today|in_progress|completed|cancelled
            $table->date('arrival_date')->nullable()->index();
            $table->date('departure_date')->nullable();
            $table->unsignedSmallInteger('total_days')->default(1);
            // Lightweight operational snapshot (customer identity may be a guest w/o user row).
            $table->string('lead_customer_name')->nullable();
            $table->string('lead_customer_phone', 32)->nullable();
            $table->string('lead_customer_email')->nullable();
            $table->string('destination_label')->nullable();
            $table->string('product_type', 20)->nullable(); // mirrors booking for quick filtering
            $table->unsignedInteger('itinerary_version')->default(1);
            $table->boolean('itinerary_generated')->default(false);
            $table->text('notes')->nullable();       // INTERNAL notes — never shown to customer
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); // admin id
            $table->timestamps();

            $table->index(['status', 'arrival_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};

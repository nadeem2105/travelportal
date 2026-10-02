<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('driver_assignments')) {
            return;
        }

        Schema::create('driver_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->string('scope', 20)->default('entire_trip'); // entire_trip|day|transfer
            $table->foreignId('trip_day_id')->nullable()->constrained('trip_days')->nullOnDelete();
            $table->foreignId('trip_event_id')->nullable()->constrained('trip_events')->nullOnDelete();
            $table->string('pickup_location')->nullable();
            $table->string('drop_location')->nullable();
            $table->dateTime('pickup_datetime')->nullable()->index();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('assigned'); // assigned|reassigned|completed|cancelled
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'status']);
            $table->index(['driver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_assignments');
    }
};

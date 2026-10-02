<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trip_days')) {
            return;
        }

        Schema::create('trip_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->date('date')->nullable();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();       // customer-facing day summary
            $table->string('hotel_snapshot')->nullable(); // resolved hotel name for the night
            $table->text('meals')->nullable();         // comma list snapshot
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['trip_id', 'day_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_days');
    }
};

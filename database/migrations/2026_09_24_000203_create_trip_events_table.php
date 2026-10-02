<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trip_events')) {
            return;
        }

        Schema::create('trip_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('trip_day_id')->nullable()->constrained('trip_days')->nullOnDelete();
            $table->unsignedSmallInteger('day_number')->default(1)->index();
            $table->string('event_type', 32)->default('activity'); // arrival|transfer|checkin|checkout|activity|meal|departure|custom
            $table->time('time')->nullable();
            $table->string('title');
            $table->string('location')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('maps_url', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('visibility', 16)->default('both'); // customer|internal|both
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['trip_id', 'day_number', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_events');
    }
};

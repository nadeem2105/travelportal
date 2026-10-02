<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('destination_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('duration_days')->default(1);
            $table->unsignedTinyInteger('duration_nights')->default(0);
            $table->string('cover_image')->nullable();
            $table->json('gallery')->nullable();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->json('highlights')->nullable();
            $table->json('inclusions')->nullable();
            $table->json('exclusions')->nullable();
            $table->json('terms')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->decimal('base_price', 10, 2); // per person
            $table->decimal('child_price', 10, 2)->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->string('package_type', 50)->default('group'); // group|private|custom
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('max_travellers')->default(20);
            $table->enum('status', ['active', 'inactive', 'draft'])->default('active');
            $table->timestamps();
        });

        Schema::create('package_itineraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('day_number');
            $table->string('title');
            $table->longText('description')->nullable();
            $table->json('meals')->nullable();
            $table->json('activities')->nullable();
            $table->string('overnight_stay')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('package_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('season', 50); // summer|winter|peak|off|custom
            $table->string('label')->nullable();
            $table->decimal('price_per_person', 10, 2)->nullable();
            $table->decimal('price_per_couple', 10, 2)->nullable();
            $table->decimal('price_per_child', 10, 2)->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('package_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->date('departure_date');
            $table->unsignedInteger('inventory')->default(20);
            $table->unsignedInteger('booked')->default(0);
            $table->decimal('price_override', 10, 2)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();
        });

        Schema::create('package_hotels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('city')->nullable();
            $table->unsignedBigInteger('hotel_id')->nullable();
            $table->string('hotel_name')->nullable();
            $table->string('room_type')->nullable();
            $table->unsignedTinyInteger('nights')->default(1);
            $table->string('category', 50)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_hotels');
        Schema::dropIfExists('package_departures');
        Schema::dropIfExists('package_prices');
        Schema::dropIfExists('package_itineraries');
        Schema::dropIfExists('packages');
    }
};

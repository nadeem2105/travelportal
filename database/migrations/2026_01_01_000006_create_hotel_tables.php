<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete(); // null = manually managed
            $table->string('supplier_code')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('destination_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->unsignedTinyInteger('star_rating')->default(3);
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->json('amenities')->nullable();
            $table->json('policies')->nullable();
            $table->json('photos')->nullable();
            $table->string('cover_image')->nullable();
            $table->decimal('starting_price', 10, 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('hotel_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('room_type');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('max_adults')->default(2);
            $table->unsignedTinyInteger('max_children')->default(1);
            $table->decimal('base_price', 10, 2);
            $table->decimal('extra_bed_price', 10, 2)->nullable();
            $table->string('meal_plan', 50)->default('room_only'); // room_only|breakfast|half_board|full_board
            $table->json('amenities')->nullable();
            $table->unsignedInteger('total_rooms')->default(5);
            $table->string('photo')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_rooms');
        Schema::dropIfExists('hotels');
    }
};

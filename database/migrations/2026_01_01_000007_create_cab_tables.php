<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cab_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->nullable()->constrained('cab_vendors')->nullOnDelete();
            $table->foreignId('vehicle_type_id')->constrained();
            $table->string('name');
            $table->string('image')->nullable();
            $table->unsignedTinyInteger('passenger_capacity')->default(4);
            $table->unsignedTinyInteger('luggage_capacity')->default(2);
            $table->boolean('is_ac')->default(true);
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('per_km_rate', 8, 2)->default(0);
            $table->decimal('per_hour_rate', 10, 2)->nullable();
            $table->json('extra_charges')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('cab_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city')->nullable();
            $table->enum('type', ['airport', 'local', 'outstation'])->default('outstation');
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cab_locations');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('vehicle_types');
        Schema::dropIfExists('cab_vendors');
    }
};

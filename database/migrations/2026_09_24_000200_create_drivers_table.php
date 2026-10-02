<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('drivers')) {
            return;
        }

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 32)->index();
            $table->string('alt_phone', 32)->nullable();
            $table->string('license_number')->nullable();
            $table->string('vehicle_number')->nullable();
            $table->string('vehicle_type')->nullable();   // sedan, suv, tempo…
            $table->string('vehicle_model')->nullable();  // e.g. Toyota Innova
            $table->string('vendor_name')->nullable();
            $table->unsignedBigInteger('cab_vendor_id')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('home_base')->nullable();       // city / hub
            $table->decimal('rating', 2, 1)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};

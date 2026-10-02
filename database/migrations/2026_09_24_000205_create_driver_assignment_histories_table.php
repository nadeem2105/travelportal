<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('driver_assignment_histories')) {
            return;
        }

        Schema::create('driver_assignment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_assignment_id')->constrained('driver_assignments')->cascadeOnDelete();
            $table->unsignedBigInteger('from_driver_id')->nullable();
            $table->unsignedBigInteger('to_driver_id')->nullable();
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_assignment_histories');
    }
};

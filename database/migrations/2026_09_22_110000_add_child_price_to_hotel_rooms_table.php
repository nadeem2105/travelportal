<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_rooms', function (Blueprint $table) {
            if (! Schema::hasColumn('hotel_rooms', 'child_price')) {
                // Per-night charge for a child aged 2-12 sharing the room (no extra bed).
                $table->decimal('child_price', 10, 2)->nullable()->after('extra_bed_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hotel_rooms', function (Blueprint $table) {
            if (Schema::hasColumn('hotel_rooms', 'child_price')) {
                $table->dropColumn('child_price');
            }
        });
    }
};

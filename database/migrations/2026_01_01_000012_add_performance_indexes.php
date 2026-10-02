<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flight_bookings', function (Blueprint $table) {
            $table->index('pnr');
            $table->index('flight_number');
        });

        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->index(['hotel_id', 'check_in', 'check_out']);
        });

        Schema::table('package_bookings', function (Blueprint $table) {
            $table->index(['package_id', 'departure_date']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('package_bookings', function (Blueprint $table) {
            $table->dropIndex(['package_id', 'departure_date']);
        });

        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->dropIndex(['hotel_id', 'check_in', 'check_out']);
        });

        Schema::table('flight_bookings', function (Blueprint $table) {
            $table->dropIndex(['pnr']);
            $table->dropIndex(['flight_number']);
        });
    }
};

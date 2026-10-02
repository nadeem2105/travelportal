<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional hotel + cab (vehicle) selections and pick-up / drop-off locations
 * on a quotation, so quotes can bundle stay + transfer alongside the package.
 * All nullable — every field stays optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_quotations', 'hotel_id')) {
                $table->unsignedBigInteger('hotel_id')->nullable()->after('package_id');
            }
            if (! Schema::hasColumn('crm_quotations', 'vehicle_id')) {
                $table->unsignedBigInteger('vehicle_id')->nullable()->after('hotel_id');
            }
            if (! Schema::hasColumn('crm_quotations', 'pickup_location')) {
                $table->string('pickup_location')->nullable()->after('vehicle_id');
            }
            if (! Schema::hasColumn('crm_quotations', 'dropoff_location')) {
                $table->string('dropoff_location')->nullable()->after('pickup_location');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            $table->dropColumn(['hotel_id', 'vehicle_id', 'pickup_location', 'dropoff_location']);
        });
    }
};

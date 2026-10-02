<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traveller (pax) counts on a quotation, so quotes show who they're for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_quotations', 'adults')) {
                $table->unsignedSmallInteger('adults')->nullable()->after('total_amount');
            }
            if (! Schema::hasColumn('crm_quotations', 'children')) {
                $table->unsignedSmallInteger('children')->nullable()->after('adults');
            }
            if (! Schema::hasColumn('crm_quotations', 'infants')) {
                $table->unsignedSmallInteger('infants')->nullable()->after('children');
            }
            if (! Schema::hasColumn('crm_quotations', 'rooms')) {
                $table->unsignedSmallInteger('rooms')->nullable()->after('infants');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            $table->dropColumn(['adults', 'children', 'infants', 'rooms']);
        });
    }
};

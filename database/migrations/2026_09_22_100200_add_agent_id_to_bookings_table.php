<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attribute bookings placed through the B2B agent portal to the booking agent,
 * and snapshot the commission earned on that booking. Additive + idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'agent_id')) {
                $table->foreignId('agent_id')->nullable()->after('user_id')->constrained('agents')->nullOnDelete();
            }
            if (! Schema::hasColumn('bookings', 'agent_commission')) {
                $table->decimal('agent_commission', 12, 2)->default(0.00)->after('agent_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'agent_id')) {
                $table->dropConstrainedForeignId('agent_id');
            }
            if (Schema::hasColumn('bookings', 'agent_commission')) {
                $table->dropColumn('agent_commission');
            }
        });
    }
};

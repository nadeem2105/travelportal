<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the admin-only `agents` table into an authenticatable entity and adds
 * KYC document / branding columns for the self-service agent portal.
 * Additive + idempotent (hasColumn-guarded) so it is safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // --- Authentication ---
            if (! Schema::hasColumn('agents', 'password')) {
                $table->string('password')->nullable()->after('email');
            }
            if (! Schema::hasColumn('agents', 'remember_token')) {
                $table->rememberToken()->after('password');
            }
            if (! Schema::hasColumn('agents', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('remember_token');
            }

            // --- KYC / documents / branding ---
            if (! Schema::hasColumn('agents', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('iata_code');
            }
            if (! Schema::hasColumn('agents', 'pan_document')) {
                $table->string('pan_document')->nullable()->after('logo_path');
            }
            if (! Schema::hasColumn('agents', 'gst_document')) {
                $table->string('gst_document')->nullable()->after('pan_document');
            }
            if (! Schema::hasColumn('agents', 'state')) {
                $table->string('state', 60)->nullable()->after('city');
            }
            if (! Schema::hasColumn('agents', 'pincode')) {
                $table->string('pincode', 12)->nullable()->after('state');
            }

            // --- Application / rejection metadata ---
            if (! Schema::hasColumn('agents', 'applied_at')) {
                $table->timestamp('applied_at')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('agents', 'rejection_reason')) {
                $table->string('rejection_reason')->nullable()->after('applied_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            foreach ([
                'password', 'remember_token', 'last_login_at', 'logo_path',
                'pan_document', 'gst_document', 'state', 'pincode',
                'applied_at', 'rejection_reason',
            ] as $col) {
                if (Schema::hasColumn('agents', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

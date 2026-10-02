<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a client-generated page_view_id column to analytics_page_views.
 *
 * This enables reliable engagement patching (time_on_page / scroll_depth)
 * even when the user bounces before the initial page view server response
 * returns. The client generates a UUID upfront and uses it as the lookup
 * key in the sendBeacon engagement patch, eliminating the race condition
 * where pageViewId was null on quick exits.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('analytics_page_views') && ! Schema::hasColumn('analytics_page_views', 'page_view_id')) {
            Schema::table('analytics_page_views', function (Blueprint $table) {
                $table->string('page_view_id', 64)->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('analytics_page_views') && Schema::hasColumn('analytics_page_views', 'page_view_id')) {
            Schema::table('analytics_page_views', function (Blueprint $table) {
                $table->dropColumn('page_view_id');
            });
        }
    }
};

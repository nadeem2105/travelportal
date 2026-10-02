<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website analytics infrastructure.
 *
 *  - analytics_sessions   : one row per visitor session (device/geo/attribution),
 *                           carries first-touch AND last-touch attribution so the
 *                           whole journey keeps its source even across navigation.
 *  - analytics_page_views : per-page hits (path, time-on-page, scroll depth, exit).
 *  - analytics_events     : EXTENDED (already exists) with event_id (dedup),
 *                           anonymous_id (guest→user stitching), value/currency
 *                           (revenue) and channel, plus a composite report index.
 *
 * Design: analytics_events is a single wide append-only "hit" table (highest
 * insert throughput). Searches / product views / leads / bookings are queried
 * from it by event_type; revenue joins the real `bookings` table. Attribution is
 * denormalised onto analytics_sessions (first_touch/last_touch JSON + flattened
 * utm_* columns for fast grouping).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('analytics_sessions')) {
            Schema::create('analytics_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('session_id', 100)->unique();          // web session id
                $table->string('anonymous_id', 64)->nullable()->index(); // persistent client id (localStorage)
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->boolean('is_returning')->default(false);

                // Device / environment (derived from UA + client hints — no PII).
                $table->string('device_type', 20)->nullable();   // mobile|tablet|desktop
                $table->string('browser', 40)->nullable();
                $table->string('os', 40)->nullable();
                $table->string('screen', 20)->nullable();         // e.g. 1920x1080
                $table->string('language', 20)->nullable();

                // Geo (best-effort; city may be blank). IP is hashed, never stored raw.
                $table->string('country', 2)->nullable()->index();
                $table->string('region', 80)->nullable();
                $table->string('city', 80)->nullable();
                $table->string('ip_hash', 64)->nullable();
                $table->string('user_agent', 512)->nullable();

                // Entry + attribution.
                $table->text('landing_page')->nullable();
                $table->text('referrer')->nullable();
                $table->string('channel', 30)->nullable()->index(); // organic|paid|social|direct|referral|email
                $table->json('first_touch')->nullable();
                $table->json('last_touch')->nullable();
                // Flattened last-touch for cheap grouping in the dashboard.
                $table->string('utm_source', 120)->nullable()->index();
                $table->string('utm_medium', 120)->nullable();
                $table->string('utm_campaign', 150)->nullable()->index();
                $table->string('utm_term', 150)->nullable();
                $table->string('utm_content', 150)->nullable();
                $table->string('gclid', 255)->nullable();
                $table->string('fbclid', 255)->nullable();

                // Rollups (kept cheap; incremented, not recomputed).
                $table->unsignedInteger('page_views')->default(0);
                $table->unsignedInteger('events_count')->default(0);

                $table->timestamp('started_at')->nullable();
                $table->timestamp('last_activity_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('analytics_page_views')) {
            Schema::create('analytics_page_views', function (Blueprint $table) {
                $table->id();
                $table->string('session_id', 100)->index();
                $table->string('anonymous_id', 64)->nullable()->index();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->text('url')->nullable();
                $table->string('path', 255)->nullable()->index();
                $table->string('title', 255)->nullable();
                $table->text('referrer')->nullable();

                $table->string('device_type', 20)->nullable();
                $table->unsignedInteger('time_on_page')->nullable();   // seconds
                $table->unsignedTinyInteger('scroll_depth')->nullable(); // percent 0..100
                $table->boolean('is_exit')->default(false);

                $table->timestamp('created_at')->useCurrent()->index();
            });
        }

        if (Schema::hasTable('analytics_events')) {
            Schema::table('analytics_events', function (Blueprint $table) {
                if (! Schema::hasColumn('analytics_events', 'event_id')) {
                    $table->string('event_id', 64)->nullable()->after('event_type')->unique();
                }
                if (! Schema::hasColumn('analytics_events', 'anonymous_id')) {
                    $table->string('anonymous_id', 64)->nullable()->after('session_id')->index();
                }
                if (! Schema::hasColumn('analytics_events', 'channel')) {
                    $table->string('channel', 30)->nullable()->after('anonymous_id');
                }
                if (! Schema::hasColumn('analytics_events', 'value')) {
                    $table->decimal('value', 12, 2)->nullable()->after('channel');
                }
                if (! Schema::hasColumn('analytics_events', 'currency')) {
                    $table->string('currency', 3)->nullable()->after('value');
                }
                if (! Schema::hasColumn('analytics_events', 'source')) {
                    $table->string('source', 20)->default('server')->after('currency'); // server|client
                }
            });

            // Composite index for the common dashboard query (type over a date range).
            Schema::table('analytics_events', function (Blueprint $table) {
                try {
                    $table->index(['event_type', 'created_at'], 'analytics_events_type_created_idx');
                } catch (\Throwable $e) {
                    // index may already exist on re-run
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_page_views');
        Schema::dropIfExists('analytics_sessions');

        if (Schema::hasTable('analytics_events')) {
            Schema::table('analytics_events', function (Blueprint $table) {
                try {
                    $table->dropIndex('analytics_events_type_created_idx');
                } catch (\Throwable $e) {
                }
                foreach (['event_id', 'anonymous_id', 'channel', 'value', 'currency', 'source'] as $col) {
                    if (Schema::hasColumn('analytics_events', $col)) {
                        try {
                            $table->dropColumn($col);
                        } catch (\Throwable $e) {
                        }
                    }
                }
            });
        }
    }
};

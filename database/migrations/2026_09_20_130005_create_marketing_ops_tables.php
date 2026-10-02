<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operational tables: sync run history, alerts, reusable campaign templates, and
 * a campaign change/audit trail. Webhook idempotency reuses the existing
 * `webhook_events` table (provider + event_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('entity', 30);      // accounts|campaigns|groups|ads|metrics|leads|conversions
            $table->foreignId('account_id')->nullable()->constrained('marketing_accounts')->nullOnDelete();
            $table->string('status', 20)->default('running'); // running|success|failed
            $table->unsignedInteger('records')->default(0);
            $table->string('cursor')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'entity', 'status']);
        });

        Schema::create('marketing_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);        // cpl_spike|spend_spike|lead_drop|roas_drop|campaign_rejected|token_expiry|api_error|creative_fatigue|...
            $table->string('severity', 10)->default('warning'); // info|warning|critical
            $table->foreignId('campaign_id')->nullable()->constrained('marketing_campaigns')->nullOnDelete();
            $table->string('title');
            $table->text('message')->nullable();
            $table->json('data')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_resolved']);
        });

        Schema::create('marketing_campaign_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('platform', 20)->nullable();
            $table->string('objective', 40)->nullable();
            $table->string('service', 20)->nullable();     // package|hotel|flight|cab
            $table->string('destination')->nullable();
            $table->decimal('default_daily_budget', 12, 2)->nullable();
            $table->json('targeting')->nullable();
            $table->json('creative_structure')->nullable();
            $table->string('landing_page')->nullable();
            $table->json('tracking')->nullable();
            $table->json('automation')->nullable();
            $table->json('kpis')->nullable();
            $table->string('status', 20)->default('active'); // active|archived
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['platform', 'status']);
        });

        Schema::create('marketing_campaign_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
            $table->string('field', 60);       // budget|targeting|status|creative|edit|sync
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('source', 20)->default('admin'); // admin|ai|sync|automation
            $table->foreignId('changed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['campaign_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaign_changes');
        Schema::dropIfExists('marketing_campaign_templates');
        Schema::dropIfExists('marketing_alerts');
        Schema::dropIfExists('marketing_sync_runs');
    }
};

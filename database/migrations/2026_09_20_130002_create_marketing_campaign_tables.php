<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign hierarchy mirrored from the ad platforms. `marketing_campaign_groups`
 * doubles as Google Ad Groups and Meta Ad Sets. Local IDs are ours; external_*
 * columns hold the provider resource IDs (never reuse a local id as external).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('marketing_accounts')->nullOnDelete();
            $table->string('provider', 20);
            $table->string('external_campaign_id')->nullable();
            $table->string('name');
            $table->string('objective', 40)->nullable();
            $table->string('status', 30)->default('draft');          // local lifecycle
            $table->string('external_status', 30)->nullable();        // provider status
            $table->string('approval_status', 30)->default('draft');  // draft|pending_review|approved|ready|published|rejected|archived
            // Travel linkage (references existing products — not authoritative)
            $table->string('product_type', 20)->nullable();           // package|hotel|flight|cab|custom
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('destination')->nullable();
            $table->string('landing_page')->nullable();
            // Budget & schedule
            $table->string('budget_type', 20)->nullable();            // daily|lifetime
            $table->decimal('daily_budget', 12, 2)->nullable();
            $table->decimal('lifetime_budget', 12, 2)->nullable();
            $table->string('currency', 8)->default('INR');
            $table->string('bidding_strategy', 40)->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            // KPI targets
            $table->decimal('target_cpl', 12, 2)->nullable();
            $table->decimal('target_roas', 8, 2)->nullable();
            $table->unsignedInteger('target_leads')->nullable();
            // Tracking
            $table->string('utm_campaign')->nullable();
            $table->json('naming_parts')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
            $table->index('external_campaign_id');
            $table->index(['product_type', 'product_id']);
        });

        Schema::create('marketing_campaign_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
            $table->string('external_id')->nullable();   // ad group / ad set id
            $table->string('name');
            $table->string('status', 30)->default('draft');
            $table->string('external_status', 30)->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->json('targeting')->nullable();        // audience/targeting snapshot
            $table->timestamps();

            $table->index('campaign_id');
            $table->index('external_id');
        });

        Schema::create('marketing_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('marketing_campaign_groups')->nullOnDelete();
            $table->foreignId('creative_id')->nullable();
            $table->string('external_id')->nullable();
            $table->string('name');
            $table->string('status', 30)->default('draft');
            $table->string('external_status', 30)->nullable();
            $table->json('copy')->nullable();             // headlines/descriptions/primary_text/cta
            $table->timestamps();

            $table->index('campaign_id');
            $table->index('external_id');
        });

        Schema::create('marketing_creatives', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('image'); // image|video|carousel|copy
            $table->string('product_type', 20)->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('destination')->nullable();
            $table->json('copy')->nullable();
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->json('tags')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'status']);
        });

        Schema::create('marketing_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('dimensions', 30)->nullable();
            $table->unsignedInteger('duration')->nullable(); // video seconds
            $table->string('category', 30)->default('image'); // image|video|ai_image|ai_video|logo|banner|product|campaign
            $table->boolean('ai_generated')->default(false);
            $table->string('provider', 40)->nullable();
            $table->unsignedBigInteger('ai_generation_id')->nullable();
            $table->json('tags')->nullable();
            $table->string('status', 20)->default('ready');
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['category', 'status']);
        });

        Schema::create('marketing_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('marketing_accounts')->nullOnDelete();
            $table->string('provider', 20);
            $table->string('external_id')->nullable();
            $table->string('name');
            $table->string('type', 30)->nullable();  // custom|lookalike|remarketing|interest|saved
            $table->json('definition')->nullable();
            $table->timestamps();

            $table->index(['provider', 'type']);
        });

        Schema::create('marketing_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->nullable()->constrained('marketing_campaign_groups')->nullOnDelete();
            $table->string('text');
            $table->string('match_type', 20)->default('broad'); // broad|phrase|exact
            $table->boolean('is_negative')->default(false);
            $table->string('external_id')->nullable();
            $table->timestamps();

            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_keywords');
        Schema::dropIfExists('marketing_audiences');
        Schema::dropIfExists('marketing_assets');
        Schema::dropIfExists('marketing_creatives');
        Schema::dropIfExists('marketing_ads');
        Schema::dropIfExists('marketing_campaign_groups');
        Schema::dropIfExists('marketing_campaigns');
    }
};

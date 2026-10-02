<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily aggregated metrics (one row per entity per day) for fast reporting,
 * lifecycle conversions, and campaign→revenue attribution links. Existing
 * booking/CRM tables remain authoritative; these only reference them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('provider', 20);
            $table->foreignId('account_id')->nullable()->constrained('marketing_accounts')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('marketing_campaigns')->nullOnDelete();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('ad_id')->nullable();
            $table->decimal('spend', 14, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedInteger('leads')->default(0);
            $table->unsignedInteger('conversions')->default(0);
            $table->decimal('conversion_value', 14, 2)->default(0);
            $table->decimal('frequency', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['date', 'provider', 'campaign_id', 'group_id', 'ad_id'], 'mdm_unique');
            $table->index(['campaign_id', 'date']);
            $table->index(['provider', 'date']);
        });

        Schema::create('marketing_conversions', function (Blueprint $table) {
            $table->id();
            $table->string('event', 40);  // lead|qualified_lead|quotation|quotation_accepted|payment_started|booking_confirmed|travel_completed
            $table->foreignId('campaign_id')->nullable()->constrained('marketing_campaigns')->nullOnDelete();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->decimal('value', 14, 2)->nullable();
            $table->string('currency', 8)->default('INR');
            $table->boolean('sent_to_provider')->default(false); // offline conversion upload flag
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['event', 'occurred_at']);
            $table->index('campaign_id');
            $table->index('lead_id');
        });

        Schema::create('marketing_attributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->foreignId('campaign_id')->nullable()->constrained('marketing_campaigns')->nullOnDelete();
            $table->string('provider', 20)->nullable();
            $table->string('external_campaign_id')->nullable();
            $table->string('external_group_id')->nullable();
            $table->string('external_ad_id')->nullable();
            $table->string('creative_ref')->nullable();
            $table->string('model', 20)->default('last_touch'); // first_touch|last_touch|assisted
            // Revenue linkage
            $table->decimal('quoted_value', 14, 2)->nullable();
            $table->decimal('booking_value', 14, 2)->nullable();
            $table->decimal('paid_value', 14, 2)->nullable();
            $table->decimal('refunded_value', 14, 2)->nullable();
            $table->decimal('net_value', 14, 2)->nullable();
            $table->timestamps();

            $table->index('lead_id');
            $table->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_attributions');
        Schema::dropIfExists('marketing_conversions');
        Schema::dropIfExists('marketing_daily_metrics');
    }
};

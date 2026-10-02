<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade the existing lightweight `crm_leads` into a full CRM lead WITHOUT
 * breaking the current CRM. Every column is nullable/defaulted and the existing
 * `source`/`status`/`budget`/`travel_date` columns are LEFT IN PLACE for
 * backward compatibility (new `source_id`/`stage_id` layer on top).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            // Identity
            $table->uuid('uuid')->nullable()->after('id');
            $table->string('lead_number')->nullable()->after('uuid');

            // Relationships (new CRM layer)
            $table->unsignedBigInteger('contact_id')->nullable()->after('lead_number');
            $table->unsignedBigInteger('company_id')->nullable()->after('contact_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source');
            $table->string('source_detail')->nullable()->after('source_id');
            $table->unsignedBigInteger('pipeline_id')->nullable()->after('status');
            $table->unsignedBigInteger('stage_id')->nullable()->after('pipeline_id');
            $table->unsignedInteger('score')->default(0)->after('stage_id');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->after('score');

            // Richer trip requirement fields (existing budget/travel_date kept)
            $table->date('travel_start_date')->nullable()->after('travel_date');
            $table->date('travel_end_date')->nullable()->after('travel_start_date');
            $table->unsignedTinyInteger('adults')->nullable()->after('travel_end_date');
            $table->unsignedTinyInteger('children')->nullable()->after('adults');
            $table->unsignedTinyInteger('infants')->nullable()->after('children');
            $table->unsignedTinyInteger('rooms')->nullable()->after('infants');
            $table->decimal('budget_min', 12, 2)->nullable()->after('budget');
            $table->decimal('budget_max', 12, 2)->nullable()->after('budget_min');
            $table->string('service_type')->nullable()->after('budget_max');

            // Attribution (first + last touch — never overwrite first touch)
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('gclid')->nullable();
            $table->string('fbclid')->nullable();
            $table->string('landing_page', 1000)->nullable();
            $table->string('referrer_url', 1000)->nullable();
            $table->string('first_touch_source')->nullable();
            $table->string('first_touch_medium')->nullable();
            $table->string('first_touch_campaign')->nullable();
            $table->string('last_touch_source')->nullable();
            $table->string('last_touch_medium')->nullable();
            $table->string('last_touch_campaign')->nullable();

            // External marketing ids (used from Meta/Google phases later)
            $table->string('external_lead_id')->nullable();
            $table->string('external_campaign_id')->nullable();
            $table->string('external_adset_id')->nullable();
            $table->string('external_ad_id')->nullable();
            $table->string('form_id')->nullable();

            // Lifecycle timestamps
            $table->timestamp('first_contacted_at')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->string('lost_reason')->nullable();

            $table->softDeletes();

            // Indexes for common CRM queries
            $table->index('contact_id');
            $table->index('source_id');
            $table->index('pipeline_id');
            $table->index('stage_id');
            $table->index('score');
            $table->index('next_follow_up_at');
            $table->index('external_lead_id');
            $table->index('lead_number');
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'uuid', 'lead_number', 'contact_id', 'company_id', 'source_id', 'source_detail',
                'pipeline_id', 'stage_id', 'score', 'priority',
                'travel_start_date', 'travel_end_date', 'adults', 'children', 'infants', 'rooms',
                'budget_min', 'budget_max', 'service_type',
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
                'gclid', 'fbclid', 'landing_page', 'referrer_url',
                'first_touch_source', 'first_touch_medium', 'first_touch_campaign',
                'last_touch_source', 'last_touch_medium', 'last_touch_campaign',
                'external_lead_id', 'external_campaign_id', 'external_adset_id', 'external_ad_id', 'form_id',
                'first_contacted_at', 'last_contacted_at', 'next_follow_up_at', 'converted_at', 'lost_at', 'lost_reason',
            ]);
        });
    }
};

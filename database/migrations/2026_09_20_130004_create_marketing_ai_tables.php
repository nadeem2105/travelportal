<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI + optimization layer: recommendations, an optimization rule engine with
 * run history, A/B experiments, and full logging of every AI generation and
 * AI-driven write action (provider/model/tokens/cost). No provider keys stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained('marketing_campaigns')->nullOnDelete();
            $table->string('category', 40);   // budget|targeting|creative|keyword|copy|landing_page|structure|lead_followup|conversion|tracking
            $table->string('title');
            $table->text('reason')->nullable();
            $table->json('evidence')->nullable();
            $table->text('suggested_action')->nullable();
            $table->string('expected_impact')->nullable();
            $table->string('confidence', 10)->nullable(); // high|medium|low
            $table->string('risk', 10)->nullable();
            $table->string('status', 20)->default('new'); // new|reviewed|approved|rejected|applied|dismissed
            $table->json('proposed_changes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'category']);
            $table->index('campaign_id');
        });

        Schema::create('marketing_optimization_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('platform', 20)->nullable();
            $table->string('metric', 40);        // cpl|ctr|cpc|cpm|roas|spend|leads
            $table->string('operator', 10);      // gt|lt|gte|lte
            $table->decimal('threshold', 14, 4);
            $table->unsignedInteger('minimum_data')->default(0); // e.g. min leads/impressions
            $table->string('action', 40);        // recommend_creative|generate_variants|pause_ad|add_negative|alert|adjust_budget
            $table->json('action_config')->nullable();
            $table->boolean('approval_required')->default(true);
            $table->unsignedInteger('cooldown_hours')->default(24);
            $table->boolean('enabled')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['enabled', 'metric']);
        });

        Schema::create('marketing_optimization_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->nullable()->constrained('marketing_optimization_rules')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('marketing_campaigns')->nullOnDelete();
            $table->string('status', 20)->default('detected'); // detected|recommended|applied|skipped
            $table->json('snapshot')->nullable();  // metrics at evaluation time
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();

            $table->index(['rule_id', 'created_at']);
        });

        Schema::create('marketing_experiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained('marketing_campaigns')->nullOnDelete();
            $table->string('name');
            $table->string('dimension', 30); // headline|primary_text|image|video|cta|landing_page|audience|offer
            $table->string('status', 20)->default('running'); // running|completed|stopped
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('winner')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_experiment_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_id')->constrained('marketing_experiments')->cascadeOnDelete();
            $table->string('label', 10); // A|B|C
            $table->json('config')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_ai_generations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('model')->nullable();
            $table->string('generation_type', 40); // campaign|copy|keywords|audience|analysis|image|video|recommendation
            $table->string('prompt_version', 20)->nullable();
            $table->string('input_context_hash', 64)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('estimated_cost', 12, 4)->nullable();
            $table->json('result')->nullable();
            $table->string('status', 20)->default('completed'); // queued|processing|completed|failed
            $table->foreignId('campaign_id')->nullable()->constrained('marketing_campaigns')->nullOnDelete();
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['generation_type', 'status']);
            $table->index(['provider', 'created_at']);
        });

        Schema::create('marketing_ai_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('provider', 30)->nullable();
            $table->string('model')->nullable();
            $table->string('action', 60);          // the write action AI proposed/performed
            $table->text('input_summary')->nullable();
            $table->text('output_summary')->nullable();
            $table->foreignId('campaign_id')->nullable()->constrained('marketing_campaigns')->nullOnDelete();
            $table->json('changes_proposed')->nullable();
            $table->json('changes_applied')->nullable();
            $table->string('approval_status', 20)->default('pending'); // pending|approved|rejected|applied
            $table->timestamps();

            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_ai_audits');
        Schema::dropIfExists('marketing_ai_generations');
        Schema::dropIfExists('marketing_experiment_variants');
        Schema::dropIfExists('marketing_experiments');
        Schema::dropIfExists('marketing_optimization_runs');
        Schema::dropIfExists('marketing_optimization_rules');
        Schema::dropIfExists('marketing_recommendations');
    }
};

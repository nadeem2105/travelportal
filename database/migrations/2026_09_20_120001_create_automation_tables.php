<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM automation engine: workflows (trigger + conditions), their ordered
 * actions (with optional delay), and a run log for observability.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('trigger_event', 60);   // lead_created|stage_changed|quotation_accepted|... |no_activity
            $table->json('conditions')->nullable(); // {source_id, stage_id, min_score, status, service_type}
            $table->json('trigger_config')->nullable(); // e.g. no_activity => {days: 3}
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['trigger_event', 'is_active']);
        });

        Schema::create('automation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('automation_workflows')->cascadeOnDelete();
            $table->string('type', 40);            // send_whatsapp|send_email|create_task|change_stage|assign_agent|add_tag|add_note
            $table->json('config')->nullable();    // type-specific settings
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['workflow_id', 'position']);
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('automation_workflows')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('trigger_event', 60);
            $table->string('status', 20)->default('completed'); // completed|failed|skipped
            $table->json('log')->nullable();       // per-action outcomes
            $table->timestamps();

            $table->index(['workflow_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_actions');
        Schema::dropIfExists('automation_workflows');
    }
};

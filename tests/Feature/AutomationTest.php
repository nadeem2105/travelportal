<?php

namespace Tests\Feature;

use App\Jobs\RunAutomationAction;
use App\Models\AutomationWorkflow;
use App\Models\CrmTask;
use App\Models\PipelineStage;
use App\Services\Crm\AutomationEngine;
use App\Services\Crm\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutomationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function makeLead(array $overrides = [])
    {
        return app(LeadService::class)->create(array_merge([
            'name' => 'Auto Lead', 'phone' => '9876500911', 'email' => 'auto@ex.com',
            'destination' => 'Kashmir', 'product_type' => 'package', 'source_slug' => 'website',
        ], $overrides));
    }

    private function workflow(string $event, array $actions, array $conditions = []): AutomationWorkflow
    {
        $wf = AutomationWorkflow::create([
            'name' => 'WF', 'trigger_event' => $event, 'is_active' => true, 'conditions' => $conditions ?: null,
        ]);
        foreach ($actions as $pos => $a) {
            $wf->actions()->create(['type' => $a['type'], 'config' => $a['config'] ?? [], 'delay_minutes' => $a['delay'] ?? 0, 'position' => $pos]);
        }

        return $wf;
    }

    public function test_create_task_action_runs_immediately(): void
    {
        $wf = $this->workflow('lead_created', [
            ['type' => 'create_task', 'config' => ['title' => 'Call {{name}}', 'due_in_days' => 1]],
        ]);

        $lead = $this->makeLead();
        // LeadService already dispatches 'lead_created' — a task should exist.
        $this->assertDatabaseHas('crm_tasks', ['lead_id' => $lead->id, 'title' => 'Call Auto Lead']);
        $this->assertSame(1, $wf->fresh()->run_count);
        $this->assertDatabaseHas('automation_runs', ['workflow_id' => $wf->id, 'lead_id' => $lead->id, 'status' => 'completed']);
    }

    public function test_add_note_and_change_stage_actions(): void
    {
        $stage = PipelineStage::orderByDesc('id')->first();
        $wf = $this->workflow('lead_created', [
            ['type' => 'add_note', 'config' => ['note' => 'Auto welcome']],
            ['type' => 'change_stage', 'config' => ['stage_id' => $stage->id]],
        ]);

        $lead = $this->makeLead();

        $this->assertDatabaseHas('crm_activities', ['lead_id' => $lead->id, 'type' => 'note', 'description' => 'Auto welcome']);
        $this->assertSame((int) $stage->id, (int) $lead->fresh()->stage_id);
    }

    public function test_conditions_filter_non_matching_leads(): void
    {
        // Only fire for flight leads; our lead is a package → should NOT fire.
        $wf = $this->workflow('lead_created', [
            ['type' => 'create_task', 'config' => ['title' => 'Should not run']],
        ], ['service_type' => 'flight']);

        $this->makeLead(['product_type' => 'package']);

        $this->assertDatabaseMissing('crm_tasks', ['title' => 'Should not run']);
        $this->assertSame(0, $wf->fresh()->run_count);
    }

    public function test_delayed_action_is_queued(): void
    {
        Queue::fake();

        $wf = $this->workflow('lead_created', [
            ['type' => 'add_note', 'config' => ['note' => 'later'], 'delay' => 1440],
        ]);

        $this->makeLead();

        Queue::assertPushed(RunAutomationAction::class);
    }

    public function test_engine_dispatch_is_safe_with_no_workflows(): void
    {
        $lead = $this->makeLead();
        // No workflow for this event — should be a no-op, no exception.
        app(AutomationEngine::class)->dispatch('booking_confirmed', $lead);
        $this->assertTrue(true);
    }
}

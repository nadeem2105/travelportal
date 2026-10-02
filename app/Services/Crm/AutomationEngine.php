<?php

namespace App\Services\Crm;

use App\Jobs\RunAutomationAction;
use App\Models\AutomationAction;
use App\Models\AutomationRun;
use App\Models\AutomationWorkflow;
use App\Models\CrmLead;
use App\Models\CrmTask;
use App\Models\PipelineStage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The CRM automation engine. Given a trigger event and a lead, it finds active
 * matching workflows, evaluates their conditions, then runs each action either
 * immediately or queued after a delay. Every fire is recorded in automation_runs.
 */
class AutomationEngine
{
    public function __construct(
        private CrmActivityService $activity,
        private LeadAssignmentService $assignment,
    ) {
    }

    /** Entry point — fire all workflows registered for an event against a lead. */
    public function dispatch(string $event, CrmLead $lead, array $context = []): void
    {
        try {
            $workflows = AutomationWorkflow::active()->where('trigger_event', $event)->with('actions')->get();

            foreach ($workflows as $workflow) {
                if (! $this->conditionsMatch($workflow, $lead)) {
                    continue;
                }
                $this->runWorkflow($workflow, $lead, $event);
            }
        } catch (\Throwable $e) {
            // Automation must never break the triggering flow.
            Log::warning("Automation dispatch failed [{$event}]: " . $e->getMessage());
        }
    }

    public function runWorkflow(AutomationWorkflow $workflow, CrmLead $lead, string $event): void
    {
        $log = [];

        foreach ($workflow->actions as $action) {
            if ($action->delay_minutes > 0) {
                RunAutomationAction::dispatch($action->id, $lead->id)
                    ->onQueue('default')
                    ->delay(now()->addMinutes($action->delay_minutes));
                $log[] = ['action' => $action->type, 'status' => 'queued', 'delay_minutes' => $action->delay_minutes];
            } else {
                $result = $this->runAction($action, $lead);
                $log[] = ['action' => $action->type, 'status' => $result['ok'] ? 'done' : 'failed', 'detail' => $result['detail'] ?? null];
            }
        }

        $workflow->increment('run_count');
        $workflow->forceFill(['last_run_at' => now()])->save();

        AutomationRun::create([
            'workflow_id' => $workflow->id,
            'lead_id' => $lead->id,
            'contact_id' => $lead->contact_id,
            'trigger_event' => $event,
            'status' => collect($log)->contains(fn ($l) => $l['status'] === 'failed') ? 'failed' : 'completed',
            'log' => $log,
        ]);
    }

    /** Evaluate a workflow's conditions against a lead (all must pass). */
    public function conditionsMatch(AutomationWorkflow $workflow, CrmLead $lead): bool
    {
        $c = $workflow->conditions ?? [];

        if (! empty($c['source_id']) && (int) $lead->source_id !== (int) $c['source_id']) {
            return false;
        }
        if (! empty($c['stage_id']) && (int) $lead->stage_id !== (int) $c['stage_id']) {
            return false;
        }
        if (! empty($c['status']) && $lead->status !== $c['status']) {
            return false;
        }
        if (! empty($c['service_type']) && $lead->service_type !== $c['service_type'] && $lead->product_type !== $c['service_type']) {
            return false;
        }
        if (isset($c['min_score']) && $c['min_score'] !== '' && (int) $lead->score < (int) $c['min_score']) {
            return false;
        }

        return true;
    }

    /**
     * Execute a single action against the lead.
     *
     * @return array{ok:bool, detail?:string}
     */
    public function runAction(AutomationAction $action, CrmLead $lead): array
    {
        $cfg = $action->config ?? [];

        try {
            return match ($action->type) {
                'send_whatsapp' => $this->sendWhatsApp($lead, $cfg),
                'send_email' => $this->sendEmail($lead, $cfg),
                'create_task' => $this->createTask($lead, $cfg),
                'change_stage' => $this->changeStage($lead, $cfg),
                'assign_agent' => $this->assignAgent($lead, $cfg),
                'add_tag' => $this->addTag($lead, $cfg),
                'add_note' => $this->addNote($lead, $cfg),
                default => ['ok' => false, 'detail' => "Unknown action {$action->type}"],
            };
        } catch (\Throwable $e) {
            Log::warning("Automation action {$action->type} failed: " . $e->getMessage());

            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }

    private function sendWhatsApp(CrmLead $lead, array $cfg): array
    {
        if (empty($lead->phone) || empty($cfg['template'])) {
            return ['ok' => false, 'detail' => 'No phone or template'];
        }
        $params = array_values(array_filter(array_map(
            fn ($p) => $this->interpolate((string) $p, $lead),
            $cfg['params'] ?? []
        ), fn ($v) => $v !== ''));

        $result = app(\App\Services\WhatsApp\WhatsAppService::class)
            ->notifyTemplate($lead->phone, $cfg['template'], $cfg['lang'] ?? null, $params, $lead->name);

        return ['ok' => (bool) ($result['success'] ?? false), 'detail' => $result['error'] ?? null];
    }

    private function sendEmail(CrmLead $lead, array $cfg): array
    {
        $email = $lead->email ?? $lead->contact?->email;
        if (empty($email) || empty($cfg['subject'])) {
            return ['ok' => false, 'detail' => 'No email or subject'];
        }

        $mailConfig = app(\App\Services\MailConfigService::class);
        if (! $mailConfig->isEnabled()) {
            return ['ok' => false, 'detail' => 'Mail disabled'];
        }
        $mailConfig->apply();

        $subject = $this->interpolate((string) $cfg['subject'], $lead);
        $body = $this->interpolate((string) ($cfg['body'] ?? ''), $lead);

        Mail::raw($body, function ($m) use ($email, $subject) {
            $m->to($email)->subject($subject);
        });

        return ['ok' => true];
    }

    private function createTask(CrmLead $lead, array $cfg): array
    {
        CrmTask::create([
            'title' => $this->interpolate((string) ($cfg['title'] ?? 'Follow up'), $lead),
            'type' => $cfg['task_type'] ?? 'follow_up',
            'priority' => $cfg['priority'] ?? 'medium',
            'status' => 'pending',
            'lead_id' => $lead->id,
            'contact_id' => $lead->contact_id,
            'assigned_user_id' => $lead->assigned_to,
            'due_at' => isset($cfg['due_in_days']) ? now()->addDays((int) $cfg['due_in_days']) : null,
        ]);

        return ['ok' => true];
    }

    private function changeStage(CrmLead $lead, array $cfg): array
    {
        if (empty($cfg['stage_id'])) {
            return ['ok' => false, 'detail' => 'No stage'];
        }
        $stage = PipelineStage::find($cfg['stage_id']);
        if (! $stage) {
            return ['ok' => false, 'detail' => 'Stage not found'];
        }
        $from = $lead->stage;
        $lead->forceFill(['stage_id' => $stage->id])->save();
        $this->activity->forLead($lead, 'stage_changed', 'Stage changed by automation', [
            'description' => ($from?->name ?? '—') . ' → ' . $stage->name,
            'performed_by' => null,
        ]);

        return ['ok' => true];
    }

    private function assignAgent(CrmLead $lead, array $cfg): array
    {
        $agentId = ($cfg['assigned_to'] ?? null) === 'round_robin' || empty($cfg['assigned_to'])
            ? $this->assignment->resolve(['source_id' => $lead->source_id])
            : (int) $cfg['assigned_to'];

        if (! $agentId) {
            return ['ok' => false, 'detail' => 'No agent resolved'];
        }
        $lead->forceFill(['assigned_to' => $agentId])->save();
        $this->activity->forLead($lead, 'assigned', 'Assigned by automation', [
            'data' => ['assigned_to' => $agentId], 'performed_by' => null,
        ]);

        return ['ok' => true];
    }

    private function addTag(CrmLead $lead, array $cfg): array
    {
        if (empty($cfg['tag_id']) || ! $lead->contact_id) {
            return ['ok' => false, 'detail' => 'No tag or contact'];
        }
        $lead->contact?->tags()->syncWithoutDetaching([$cfg['tag_id']]);

        return ['ok' => true];
    }

    private function addNote(CrmLead $lead, array $cfg): array
    {
        $this->activity->forLead($lead, 'note', 'Automation note', [
            'description' => $this->interpolate((string) ($cfg['note'] ?? ''), $lead),
            'is_internal' => true,
            'performed_by' => null,
        ]);

        return ['ok' => true];
    }

    /** Replace {{name}}, {{destination}}, {{lead_number}} tokens in text. */
    private function interpolate(string $text, CrmLead $lead): string
    {
        $map = [
            '{{name}}' => $lead->name ?? 'there',
            '{{destination}}' => $lead->destination ?? '',
            '{{lead_number}}' => $lead->lead_number ?? '',
            '{{agent}}' => $lead->assignee?->name ?? '',
        ];

        return strtr($text, $map);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AutomationWorkflow;
use App\Models\LeadSource;
use App\Models\PipelineStage;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AutomationController extends Controller
{
    /** Trigger events the engine understands. */
    public const TRIGGERS = [
        'lead_created' => 'Lead created',
        'stage_changed' => 'Lead stage changed',
        'quotation_accepted' => 'Quotation accepted',
        'quotation_rejected' => 'Quotation declined',
        'booking_confirmed' => 'Booking confirmed',
        'whatsapp_received' => 'WhatsApp message received',
        'no_activity' => 'No activity for N days',
    ];

    public const ACTION_TYPES = [
        'send_whatsapp' => 'Send WhatsApp template',
        'send_email' => 'Send email',
        'create_task' => 'Create task',
        'change_stage' => 'Change stage',
        'assign_agent' => 'Assign agent',
        'add_tag' => 'Add tag',
        'add_note' => 'Add note',
    ];

    public function index()
    {
        $workflows = AutomationWorkflow::withCount('actions')->latest()->paginate(20);

        return view('admin.crm.automations.index', ['workflows' => $workflows, 'triggers' => self::TRIGGERS]);
    }

    public function create()
    {
        return view('admin.crm.automations.form', $this->formData(new AutomationWorkflow(['is_active' => true])));
    }

    public function store(Request $request)
    {
        $workflow = DB::transaction(function () use ($request) {
            $workflow = AutomationWorkflow::create($this->workflowAttributes($request) + ['created_by' => auth('admin')->id()]);
            $this->syncActions($workflow, $request);

            return $workflow;
        });

        return redirect()->route('admin.automations.edit', $workflow)->with('success', 'Workflow created.');
    }

    public function edit(AutomationWorkflow $automation)
    {
        $automation->load('actions');

        return view('admin.crm.automations.form', $this->formData($automation));
    }

    public function update(Request $request, AutomationWorkflow $automation)
    {
        DB::transaction(function () use ($request, $automation) {
            $automation->update($this->workflowAttributes($request));
            $automation->actions()->delete();
            $this->syncActions($automation, $request);
        });

        return redirect()->route('admin.automations.edit', $automation)->with('success', 'Workflow updated.');
    }

    public function toggle(AutomationWorkflow $automation)
    {
        $automation->update(['is_active' => ! $automation->is_active]);

        return back()->with('success', 'Workflow ' . ($automation->is_active ? 'enabled' : 'disabled') . '.');
    }

    public function destroy(AutomationWorkflow $automation)
    {
        $automation->delete();

        return redirect()->route('admin.automations.index')->with('success', 'Workflow deleted.');
    }

    public function show(AutomationWorkflow $automation)
    {
        $runs = $automation->runs()->with('lead')->paginate(30);

        return view('admin.crm.automations.runs', compact('automation', 'runs'));
    }

    // ---- helpers ------------------------------------------------------------

    private function formData(AutomationWorkflow $workflow): array
    {
        return [
            'workflow' => $workflow,
            'triggers' => self::TRIGGERS,
            'actionTypes' => self::ACTION_TYPES,
            'sources' => LeadSource::orderBy('name')->get(),
            'stages' => PipelineStage::orderBy('sort_order')->get(),
            'tags' => Tag::orderBy('name')->get(),
            'staff' => Admin::where('status', 'active')->orderBy('name')->get(),
        ];
    }

    private function workflowAttributes(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'trigger_event' => 'required|in:' . implode(',', array_keys(self::TRIGGERS)),
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
            'cond_source_id' => 'nullable|exists:lead_sources,id',
            'cond_stage_id' => 'nullable|exists:pipeline_stages,id',
            'cond_status' => 'nullable|string|max:30',
            'cond_service_type' => 'nullable|string|max:30',
            'cond_min_score' => 'nullable|integer|min:0',
            'trigger_days' => 'nullable|integer|min:1|max:365',
        ]);

        $conditions = array_filter([
            'source_id' => $validated['cond_source_id'] ?? null,
            'stage_id' => $validated['cond_stage_id'] ?? null,
            'status' => $validated['cond_status'] ?? null,
            'service_type' => $validated['cond_service_type'] ?? null,
            'min_score' => isset($validated['cond_min_score']) && $validated['cond_min_score'] !== '' ? (int) $validated['cond_min_score'] : null,
        ], fn ($v) => $v !== null && $v !== '');

        return [
            'name' => $validated['name'],
            'trigger_event' => $validated['trigger_event'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'conditions' => $conditions ?: null,
            'trigger_config' => $validated['trigger_event'] === 'no_activity'
                ? ['days' => (int) ($validated['trigger_days'] ?? 3)]
                : null,
        ];
    }

    private function syncActions(AutomationWorkflow $workflow, Request $request): void
    {
        $actions = $request->input('actions', []);
        $position = 0;

        foreach ($actions as $a) {
            $type = $a['type'] ?? null;
            if (! $type || ! array_key_exists($type, self::ACTION_TYPES)) {
                continue;
            }

            $config = match ($type) {
                'send_whatsapp' => [
                    'template' => $a['template'] ?? null,
                    'lang' => $a['lang'] ?? null,
                    'params' => array_values(array_filter(array_map('trim', explode('|', (string) ($a['params'] ?? ''))), fn ($v) => $v !== '')),
                ],
                'send_email' => ['subject' => $a['subject'] ?? '', 'body' => $a['body'] ?? ''],
                'create_task' => [
                    'title' => $a['title'] ?? 'Follow up',
                    'task_type' => $a['task_type'] ?? 'follow_up',
                    'priority' => $a['priority'] ?? 'medium',
                    'due_in_days' => isset($a['due_in_days']) && $a['due_in_days'] !== '' ? (int) $a['due_in_days'] : null,
                ],
                'change_stage' => ['stage_id' => $a['stage_id'] ?? null],
                'assign_agent' => ['assigned_to' => $a['assigned_to'] ?? 'round_robin'],
                'add_tag' => ['tag_id' => $a['tag_id'] ?? null],
                'add_note' => ['note' => $a['note'] ?? ''],
                default => [],
            };

            $workflow->actions()->create([
                'type' => $type,
                'config' => $config,
                'delay_minutes' => (int) ($a['delay_minutes'] ?? 0),
                'position' => $position++,
            ]);
        }
    }
}

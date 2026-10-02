<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\CrmLead;
use App\Models\CrmTask;
use App\Services\Crm\CrmActivityService;
use Illuminate\Http\Request;

class CrmTaskController extends Controller
{
    protected array $tabs = ['today', 'upcoming', 'overdue', 'completed'];

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), $this->tabs, true) ? $request->query('tab') : 'today';

        $tasks = $this->tabQuery($tab)
            ->with(['lead', 'contact', 'assignee'])
            ->when($request->filled('assigned_user'), fn ($q) => $q->where('assigned_user_id', $request->query('assigned_user')))
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        // Tab badge counts (respect the assigned_user filter for consistency).
        $assigned = $request->query('assigned_user');
        $counts = [];
        foreach ($this->tabs as $t) {
            $counts[$t] = $this->tabQuery($t)
                ->when($assigned, fn ($q) => $q->where('assigned_user_id', $assigned))
                ->count();
        }

        $staff = Admin::where('status', 'active')->orderBy('name')->get();

        return view('admin.crm.tasks.index', compact('tasks', 'tab', 'counts', 'staff'));
    }

    /** Build the base query for a given tab. */
    protected function tabQuery(string $tab)
    {
        return match ($tab) {
            'overdue' => CrmTask::query()->overdue(),
            'upcoming' => CrmTask::query()->pending()
                ->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '>', now()->endOfDay())),
            'completed' => CrmTask::query()->where('status', 'completed'),
            default => CrmTask::query()->dueToday(),
        };
    }

    public function store(Request $request, CrmActivityService $activity)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'type' => 'nullable|in:call,whatsapp,email,follow_up,quotation,payment_reminder,document,booking_confirmation,post_trip,other',
            'due_at' => 'nullable|date',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_user_id' => 'nullable|exists:admins,id',
            'lead_id' => 'nullable|exists:crm_leads,id',
            'contact_id' => 'nullable|exists:contacts,id',
        ]);

        $task = CrmTask::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'] ?? 'follow_up',
            'due_at' => $validated['due_at'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'assigned_user_id' => $validated['assigned_user_id'] ?? auth('admin')->id(),
            'lead_id' => $validated['lead_id'] ?? null,
            'contact_id' => $validated['contact_id'] ?? null,
            'status' => 'pending',
        ]);

        // Mirror onto the lead timeline when the task is lead-scoped.
        if ($task->lead_id && ($lead = CrmLead::find($task->lead_id))) {
            $activity->forLead($lead, 'task_created', 'Task created', [
                'description' => $task->title,
                'performed_by' => auth('admin')->id(),
            ]);
        }

        return back()->with('success', 'Task created.');
    }

    public function complete(CrmTask $task, CrmActivityService $activity)
    {
        if ($task->status !== 'completed') {
            $task->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => auth('admin')->id(),
            ]);

            $opts = [
                'description' => "Task completed: {$task->title}",
                'contact_id' => $task->contact_id,
                'performed_by' => auth('admin')->id(),
            ];

            if ($task->lead_id && ($lead = CrmLead::find($task->lead_id))) {
                $activity->forLead($lead, 'task_completed', 'Task completed', $opts);
            } else {
                $activity->record('task_completed', 'Task completed', $opts);
            }
        }

        return back()->with('success', 'Task marked complete.');
    }
}

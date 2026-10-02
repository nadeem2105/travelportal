@extends('layouts.admin')
@section('pageTitle', 'CRM Tasks')

@php
    $priorityPill = [
        'low' => 'bg-slate-100 text-slate-600', 'medium' => 'bg-sky-100 text-sky-700',
        'high' => 'bg-amber-100 text-amber-700', 'urgent' => 'bg-rose-100 text-rose-700',
    ];
    $tabLabels = ['today' => 'Today', 'upcoming' => 'Upcoming', 'overdue' => 'Overdue', 'completed' => 'Completed'];
@endphp

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <h1 class="font-display text-xl font-bold">CRM Tasks</h1>
            <p class="text-xs text-ink-500">Calls, follow-ups, reminders and to-dos across the pipeline</p>
        </div>
        <button onclick="document.getElementById('taskModal').classList.remove('hidden')" class="btn-primary btn-md">+ New Task</button>
    </div>

    {{-- Tabs --}}
    <div class="mt-4 flex flex-wrap gap-2">
        @foreach ($tabLabels as $key => $label)
            <a href="{{ route('admin.crm-tasks.index', array_filter(['tab' => $key, 'assigned_user' => request('assigned_user'), 'per_page' => request('per_page')])) }}"
               class="btn-sm {{ $tab === $key ? 'btn-primary' : 'btn-ghost' }}">
                {{ $label }}
                <span class="ml-1 rounded-full bg-black/10 px-1.5 text-[10px]">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <x-admin.filters
        :search="false"
        :filters="[
            ['name' => 'assigned_user', 'label' => 'Owner', 'all' => 'All owners', 'options' => $staff->pluck('name', 'id')->all()],
        ]"
        :count="$tasks->total()"
    >
        <input type="hidden" name="tab" value="{{ $tab }}">
    </x-admin.filters>

    <div class="admin-card mt-4 overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Type</th>
                    <th>Priority</th>
                    <th>Related to</th>
                    <th>Owner</th>
                    <th>Due</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tasks as $task)
                    <tr>
                        <td>
                            <div class="font-semibold text-ink-800">{{ $task->title }}</div>
                            @if ($task->description)<div class="max-w-xs truncate text-[11px] text-ink-400">{{ $task->description }}</div>@endif
                        </td>
                        <td class="text-xs capitalize">{{ label_case((string) $task->type) }}</td>
                        <td><span class="status-pill capitalize {{ $priorityPill[$task->priority] ?? 'bg-slate-100 text-slate-600' }}">{{ $task->priority }}</span></td>
                        <td class="text-xs">
                            @if ($task->lead)
                                <a href="{{ route('admin.crm.show', $task->lead) }}" class="font-mono text-brand-600 hover:underline">{{ $task->lead->lead_number ?? ('#' . $task->lead->id) }}</a>
                            @elseif ($task->contact)
                                <a href="{{ route('admin.contacts.show', $task->contact) }}" class="text-brand-600 hover:underline">{{ $task->contact->name }}</a>
                            @else
                                <span class="text-ink-400">—</span>
                            @endif
                        </td>
                        <td class="text-xs">{{ $task->assignee?->name ?? 'Unassigned' }}</td>
                        <td class="text-xs">
                            @if ($task->due_at)
                                <span class="{{ $task->status !== 'completed' && $task->due_at->isPast() ? 'font-semibold text-rose-600' : 'text-ink-600' }}">{{ $task->due_at->format('d M Y, h:i A') }}</span>
                            @else
                                <span class="text-ink-400">—</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @if ($task->status === 'completed')
                                <span class="status-pill bg-emerald-100 text-emerald-700">Completed</span>
                            @else
                                <form action="{{ route('admin.crm-tasks.complete', $task) }}" method="POST" class="inline">
                                    @csrf
                                    <button class="btn-ghost btn-sm text-emerald-700 hover:bg-emerald-50">✓ Complete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-ink-500">No tasks in this view.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tasks->links() }}</div>

    {{-- New task modal --}}
    <div id="taskModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-bold">Add New Task</h3>
                <button onclick="document.getElementById('taskModal').classList.add('hidden')" class="text-ink-400 hover:text-ink-600">✕</button>
            </div>
            <form action="{{ route('admin.crm-tasks.store') }}" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label class="label">Title *</label>
                    <input type="text" name="title" class="input" required placeholder="e.g. Follow up on Kashmir quotation">
                </div>
                <div>
                    <label class="label">Description</label>
                    <textarea name="description" rows="2" class="input"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Type</label>
                        <select name="type" class="input">
                            @foreach (['follow_up', 'call', 'whatsapp', 'email', 'quotation', 'payment_reminder', 'document', 'booking_confirmation', 'post_trip', 'other'] as $t)
                                <option value="{{ $t }}">{{ label_case($t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Priority</label>
                        <select name="priority" class="input">
                            @foreach (['low', 'medium', 'high', 'urgent'] as $p)
                                <option value="{{ $p }}" {{ $p === 'medium' ? 'selected' : '' }}>{{ label_case($p) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Due</label>
                        <input type="datetime-local" name="due_at" class="input text-xs">
                    </div>
                    <div>
                        <label class="label">Assign To</label>
                        <select name="assigned_user_id" class="input">
                            <option value="">Me</option>
                            @foreach ($staff as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-3">
                    <button type="button" onclick="document.getElementById('taskModal').classList.add('hidden')" class="btn-ghost btn-md">Cancel</button>
                    <button class="btn-primary btn-md">Save Task</button>
                </div>
            </form>
        </div>
    </div>
@endsection

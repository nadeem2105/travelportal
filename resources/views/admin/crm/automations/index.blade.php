@extends('layouts.admin')
@section('pageTitle', 'Automations')

@section('content')
<div class="flex items-center justify-between">
    <div>
        <h1 class="font-display text-xl font-bold">Automations</h1>
        <p class="text-xs text-ink-500">Trigger → action workflows that run automatically across the CRM</p>
    </div>
    <a href="{{ route('admin.automations.create') }}" class="btn-primary btn-sm">+ New Workflow</a>
</div>

@if (session('success'))<div class="mt-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('success') }}</div>@endif
@if (session('error'))<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ session('error') }}</div>@endif

<div class="admin-card mt-4 overflow-x-auto">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Trigger</th>
                <th class="text-center">Actions</th>
                <th class="text-center">Runs</th>
                <th>Last run</th>
                <th>Status</th>
                <th class="text-right">Manage</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($workflows as $wf)
                <tr class="{{ $wf->is_active ? '' : 'opacity-50' }}">
                    <td class="font-semibold">{{ $wf->name }}</td>
                    <td class="text-xs">{{ $triggers[$wf->trigger_event] ?? $wf->trigger_event }}</td>
                    <td class="text-center">{{ $wf->actions_count }}</td>
                    <td class="text-center">{{ $wf->run_count }}</td>
                    <td class="text-xs text-ink-500">{{ $wf->last_run_at?->diffForHumans() ?? '—' }}</td>
                    <td>
                        @if ($wf->is_active)<span class="status-pill bg-emerald-100 text-emerald-700">Active</span>
                        @else<span class="status-pill bg-slate-100 text-slate-500">Paused</span>@endif
                    </td>
                    <td class="text-right">
                        <a href="{{ route('admin.automations.runs', $wf) }}" class="btn-ghost btn-xs">Log</a>
                        <a href="{{ route('admin.automations.edit', $wf) }}" class="btn-ghost btn-xs">Edit</a>
                        <form action="{{ route('admin.automations.toggle', $wf) }}" method="POST" class="inline">@csrf
                            <button class="btn-ghost btn-xs">{{ $wf->is_active ? 'Pause' : 'Enable' }}</button>
                        </form>
                        <form action="{{ route('admin.automations.destroy', $wf) }}" method="POST" class="inline" onsubmit="return confirm('Delete this workflow?');">@csrf @method('DELETE')
                            <button class="btn-ghost btn-xs text-rose-600">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-ink-500">No workflows yet. Create one to automate follow-ups, assignments, and messages.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $workflows->links() }}</div>
@endsection

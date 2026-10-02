@extends('layouts.admin')
@section('pageTitle', 'Workflow Runs')

@section('content')
<a href="{{ route('admin.automations.index') }}" class="text-sm text-ink-500 hover:text-brand-700">← All Workflows</a>
<div class="mt-2 flex items-center justify-between">
    <div>
        <h1 class="font-display text-xl font-bold">{{ $automation->name }}</h1>
        <p class="text-xs text-ink-500">Run log · {{ $automation->run_count }} total runs</p>
    </div>
    <a href="{{ route('admin.automations.edit', $automation) }}" class="btn-ghost btn-sm">Edit workflow</a>
</div>

<div class="admin-card mt-4 overflow-x-auto">
    <table class="admin-table">
        <thead>
            <tr><th>When</th><th>Lead</th><th>Trigger</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($runs as $run)
                <tr>
                    <td class="text-xs text-ink-500">{{ $run->created_at->format('d M Y, H:i') }}</td>
                    <td class="text-xs">
                        @if ($run->lead)
                            <a href="{{ route('admin.crm.show', $run->lead) }}" class="font-mono text-brand-600 hover:underline">{{ $run->lead->lead_number ?? ('#' . $run->lead->id) }}</a>
                        @else — @endif
                    </td>
                    <td class="text-xs">{{ str_replace('_', ' ', $run->trigger_event) }}</td>
                    <td>
                        <span class="status-pill {{ $run->status === 'completed' ? 'bg-emerald-100 text-emerald-700' : ($run->status === 'failed' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600') }}">{{ $run->status }}</span>
                    </td>
                    <td class="text-xs text-ink-600">
                        @foreach ($run->log ?? [] as $entry)
                            <div>
                                <span class="font-mono">{{ str_replace('_', ' ', $entry['action'] ?? '') }}</span>
                                — <span class="{{ ($entry['status'] ?? '') === 'failed' ? 'text-rose-600' : 'text-emerald-600' }}">{{ $entry['status'] ?? '' }}</span>
                                @if (! empty($entry['detail']))<span class="text-ink-400">({{ $entry['detail'] }})</span>@endif
                            </div>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-ink-500">No runs yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $runs->links() }}</div>
@endsection

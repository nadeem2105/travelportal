@extends('layouts.admin')
@section('pageTitle', 'Activity Logs')

@section('content')
    <h1 class="font-display text-xl font-bold">Activity Logs</h1>

    <div class="mt-3 flex flex-wrap gap-2">
        @foreach (['' => 'All'] + $modules->combine($modules->map(fn ($m) => ucfirst((string) $m)))->toArray() as $key => $label)
            <a href="{{ route('admin.activity.index', array_filter(['module' => $key])) }}"
               class="rounded-full px-4 py-1.5 text-xs font-semibold {{ request('module') === $key ? 'bg-brand-600 text-white' : 'bg-white text-ink-700 ring-1 ring-slate-200' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="admin-card mt-4 overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th>Admin</th><th>Action</th><th>Module</th><th>Description</th><th>IP</th><th class="text-right">Date &amp; Time</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="font-bold">{{ $log->admin?->name ?? 'System' }}</td>
                        <td><span class="status-pill {{ status_pill_class($log->action) }}">{{ ucfirst($log->action) }}</span></td>
                        <td class="capitalize">{{ $log->module ?? '—' }}</td>
                        <td class="max-w-[320px] truncate">{{ $log->description }}</td>
                        <td class="font-mono text-xs">{{ $log->ip_address }}</td>
                        <td class="text-right text-xs text-ink-500">{{ $log->created_at->format('d M Y, H:i:s') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-ink-500">No activity logged yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection

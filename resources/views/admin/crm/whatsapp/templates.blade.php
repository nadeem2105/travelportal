@extends('layouts.admin')
@section('pageTitle', 'WhatsApp Templates')

@section('content')
<div class="flex items-center justify-between">
    <div>
        <h1 class="font-display text-xl font-bold">WhatsApp Templates</h1>
        <p class="text-xs text-ink-500">Approved message templates synced from your Meta WhatsApp Business account</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.whatsapp-templates.create') }}" class="btn-primary btn-sm">+ Create Template</a>
        <form action="{{ route('admin.whatsapp-templates.sync') }}" method="POST">
            @csrf
            <button class="btn-ghost btn-sm">↻ Sync from Meta</button>
        </form>
    </div>
</div>

@if (session('success'))<div class="mt-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('success') }}</div>@endif
@if (session('error'))<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ session('error') }}</div>@endif

<div class="admin-card mt-4 overflow-x-auto">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Language</th>
                <th>Category</th>
                <th>Status</th>
                <th class="text-center">Body vars</th>
                <th>Preview</th>
                <th>Synced</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($templates as $t)
                <tr>
                    <td class="font-mono text-xs font-semibold">{{ $t->name }}</td>
                    <td class="text-xs">{{ $t->language }}</td>
                    <td class="text-xs">{{ $t->category ?? '—' }}</td>
                    <td>
                        <span class="status-pill {{ $t->isApproved() ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ $t->status }}</span>
                    </td>
                    <td class="text-center">{{ $t->body_variable_count }}</td>
                    <td class="max-w-md text-xs text-ink-500">{{ \Illuminate\Support\Str::limit($t->body_preview, 120) }}</td>
                    <td class="text-xs text-ink-400">{{ $t->synced_at?->diffForHumans() ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-ink-500">No templates yet. Click "Sync from Meta" to pull your approved templates.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $templates->links() }}</div>
@endsection

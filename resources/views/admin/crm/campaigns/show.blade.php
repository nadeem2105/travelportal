@extends('layouts.admin')
@section('pageTitle', $whatsappCampaign->name)

@php
    $c = $whatsappCampaign;
    $statusPill = [
        'draft' => 'bg-slate-100 text-slate-600', 'scheduled' => 'bg-sky-100 text-sky-700',
        'sending' => 'bg-amber-100 text-amber-700', 'completed' => 'bg-emerald-100 text-emerald-700',
        'failed' => 'bg-rose-100 text-rose-700', 'cancelled' => 'bg-ink-100 text-ink-500',
    ];
    $recipientPill = [
        'pending' => 'bg-slate-100 text-slate-500', 'sent' => 'bg-sky-100 text-sky-700',
        'delivered' => 'bg-indigo-100 text-indigo-700', 'read' => 'bg-emerald-100 text-emerald-700',
        'failed' => 'bg-rose-100 text-rose-700', 'skipped' => 'bg-ink-100 text-ink-400',
    ];
@endphp

@section('content')
<a href="{{ route('admin.whatsapp-campaigns.index') }}" class="text-sm text-ink-500 hover:text-brand-700">← All Campaigns</a>

<div class="mt-2 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-xl font-bold">{{ $c->name }}</h1>
        <p class="text-xs text-ink-500">
            <span class="status-pill capitalize {{ $statusPill[$c->status] ?? '' }}">{{ $c->status }}</span>
            · Template <span class="font-mono">{{ $c->template_name }}</span> ({{ $c->template_language }})
            · Group {{ $c->group->name ?? '—' }}
        </p>
    </div>
    <div class="flex items-center gap-2">
        @if ($c->isEditable())
            <form action="{{ route('admin.whatsapp-campaigns.send', $c) }}" method="POST" onsubmit="return confirm('Send this campaign now to {{ $estimated }} recipient(s)?');">
                @csrf
                <button class="btn-primary btn-sm">Send Now ({{ $estimated }})</button>
            </form>
        @endif
        @if ($c->status === 'sending')
            <form action="{{ route('admin.whatsapp-campaigns.refresh', $c) }}" method="POST">
                @csrf
                <button class="btn-secondary btn-sm" title="Recheck recipient statuses and progress">Refresh Progress</button>
            </form>
        @endif
        @if (in_array($c->status, ['sending', 'failed', 'completed'], true) && ($c->failed_count > 0 || $recipients->where('status', 'pending')->count() > 0))
            <form action="{{ route('admin.whatsapp-campaigns.retry', $c) }}" method="POST" onsubmit="return confirm('Retry delivery for pending or failed recipients?');">
                @csrf
                <button class="btn-secondary btn-sm text-brand-700" title="Re-queue failed or pending recipients">Retry Failed / Stuck</button>
            </form>
        @endif
        @if (in_array($c->status, ['draft','scheduled','sending'], true))
            <form action="{{ route('admin.whatsapp-campaigns.cancel', $c) }}" method="POST" onsubmit="return confirm('Cancel this campaign?');">
                @csrf
                <button class="btn-ghost btn-sm text-rose-600">Cancel</button>
            </form>
        @endif
    </div>
</div>

@if (session('success'))<div class="mt-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('success') }}</div>@endif
@if (session('error'))<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ session('error') }}</div>@endif

@if ($c->status === 'sending')
    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
        <strong>Dispatching:</strong> Campaign messages are queued for rate-limited delivery. If progress is not moving, ensure your background worker is running:
        <code class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 font-mono font-semibold">php artisan queue:work --queue=whatsapp,default</code>
    </div>
@endif

{{-- Progress counters --}}
<div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
    @foreach ([['Total', $c->total_recipients ?: $estimated, 'text-ink-800'], ['Sent', $c->sent_count, 'text-sky-700'], ['Delivered', $c->delivered_count, 'text-indigo-700'], ['Read', $c->read_count, 'text-emerald-700'], ['Failed', $c->failed_count, 'text-rose-700']] as [$label, $val, $cls])
        <div class="admin-card p-4 text-center">
            <div class="text-2xl font-bold {{ $cls }}">{{ $val }}</div>
            <div class="text-xs text-ink-500">{{ $label }}</div>
        </div>
    @endforeach
</div>

{{-- Schedule (only when still editable) --}}
@if ($c->isEditable())
    <div class="admin-card mt-4 p-4">
        <form action="{{ route('admin.whatsapp-campaigns.schedule', $c) }}" method="POST" class="flex flex-wrap items-end gap-2">
            @csrf
            <div>
                <label class="label">Schedule for later</label>
                <input type="datetime-local" name="scheduled_at" class="input" value="{{ old('scheduled_at', $c->scheduled_at?->format('Y-m-d\TH:i')) }}">
            </div>
            <button class="btn-ghost btn-sm">{{ $c->status === 'scheduled' ? 'Reschedule' : 'Schedule' }}</button>
            @if ($c->status === 'scheduled' && $c->scheduled_at)
                <span class="text-xs text-sky-700">Scheduled for {{ $c->scheduled_at->format('d M Y, H:i') }}</span>
            @endif
        </form>
    </div>
@endif

{{-- Recipients --}}
<div class="admin-card mt-4 overflow-x-auto">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Contact</th>
                <th>Number</th>
                <th>Status</th>
                <th>Sent</th>
                <th>Error</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recipients as $r)
                <tr>
                    <td class="text-sm">{{ $r->contact->name ?? '—' }}</td>
                    <td class="font-mono text-xs">{{ $r->wa_id }}</td>
                    <td><span class="status-pill capitalize {{ $recipientPill[$r->status] ?? '' }}">{{ $r->status }}</span></td>
                    <td class="text-xs text-ink-500">{{ $r->sent_at?->format('d M, H:i') ?? '—' }}</td>
                    <td class="text-xs text-rose-600">{{ \Illuminate\Support\Str::limit($r->error, 60) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-ink-500">No recipients materialized yet. They're built when you send or schedule.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $recipients->links() }}</div>
@endsection

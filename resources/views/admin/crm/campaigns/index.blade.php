@extends('layouts.admin')
@section('pageTitle', 'WhatsApp Campaigns')

@php
    $statusPill = [
        'draft' => 'bg-slate-100 text-slate-600', 'scheduled' => 'bg-sky-100 text-sky-700',
        'sending' => 'bg-amber-100 text-amber-700', 'completed' => 'bg-emerald-100 text-emerald-700',
        'failed' => 'bg-rose-100 text-rose-700', 'cancelled' => 'bg-ink-100 text-ink-500',
    ];
@endphp

@section('content')
<div class="flex items-center justify-between">
    <div>
        <h1 class="font-display text-xl font-bold">WhatsApp Campaigns</h1>
        <p class="text-xs text-ink-500">Broadcast approved templates to contact groups</p>
    </div>
    <a href="{{ route('admin.whatsapp-campaigns.create') }}" class="btn-primary btn-sm">+ New Campaign</a>
</div>

@if (session('success'))<div class="mt-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('success') }}</div>@endif
@if (session('error'))<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ session('error') }}</div>@endif

<div class="admin-card mt-4 overflow-x-auto">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Group</th>
                <th>Template</th>
                <th>Status</th>
                <th class="text-center">Sent / Total</th>
                <th>When</th>
                <th class="text-right">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($campaigns as $c)
                <tr>
                    <td><a href="{{ route('admin.whatsapp-campaigns.show', $c) }}" class="font-semibold text-brand-600 hover:underline">{{ $c->name }}</a></td>
                    <td class="text-xs">{{ $c->group->name ?? '—' }}</td>
                    <td class="font-mono text-xs">{{ $c->template_name }}</td>
                    <td><span class="status-pill capitalize {{ $statusPill[$c->status] ?? 'bg-slate-100' }}">{{ $c->status }}</span></td>
                    <td class="text-center text-xs">{{ $c->sent_count }} / {{ $c->total_recipients }}</td>
                    <td class="text-xs text-ink-500">
                        @if ($c->status === 'scheduled' && $c->scheduled_at) {{ $c->scheduled_at->format('d M, H:i') }}
                        @elseif ($c->completed_at) {{ $c->completed_at->format('d M, H:i') }}
                        @else {{ $c->created_at->diffForHumans() }} @endif
                    </td>
                    <td class="text-right"><a href="{{ route('admin.whatsapp-campaigns.show', $c) }}" class="btn-ghost btn-sm">View</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-ink-500">No campaigns yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $campaigns->links() }}</div>
@endsection

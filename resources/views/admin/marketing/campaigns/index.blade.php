@extends('layouts.admin')
@section('pageTitle', 'Campaigns')

@php
    $inr = fn ($n) => '₹' . number_format((float) $n, 0);
    $statusPill = [
        'draft' => 'bg-slate-100 text-slate-600', 'published' => 'bg-sky-100 text-sky-700',
        'active' => 'bg-emerald-100 text-emerald-700', 'paused' => 'bg-amber-100 text-amber-700',
        'completed' => 'bg-indigo-100 text-indigo-700', 'rejected' => 'bg-rose-100 text-rose-700',
    ];
@endphp

@section('content')
<div class="flex items-center justify-between">
    <div>
        <h1 class="font-display text-xl font-bold">Campaigns</h1>
        <p class="text-xs text-ink-500">Draft, publish (paused) and manage ad campaigns</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.marketing.ai.campaign') }}" class="btn-ghost btn-sm">✨ Create with AI</a>
        <a href="{{ route('admin.marketing.campaigns.create') }}" class="btn-primary btn-sm">+ Create Manually</a>
    </div>
</div>

@if (session('success'))<div class="mt-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('success') }}</div>@endif
@if (session('error'))<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ session('error') }}</div>@endif

<form method="GET" class="admin-card mt-4 flex flex-wrap items-end gap-2 p-3">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name…" class="input text-sm">
    <select name="provider" class="input text-sm">
        <option value="">All platforms</option>
        <option value="google_ads" @selected(request('provider')==='google_ads')>Google Ads</option>
        <option value="meta_ads" @selected(request('provider')==='meta_ads')>Meta Ads</option>
    </select>
    <select name="status" class="input text-sm">
        <option value="">All statuses</option>
        @foreach (['draft','published','active','paused','completed'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>@endforeach
    </select>
    <button class="btn-ghost btn-sm">Filter</button>
</form>

<div class="admin-card mt-4 overflow-x-auto">
    <table class="admin-table">
        <thead>
            <tr><th>Name</th><th>Platform</th><th>Objective</th><th>Status</th><th>Daily Budget</th><th>Created</th><th class="text-right">Action</th></tr>
        </thead>
        <tbody>
            @forelse ($campaigns as $c)
                <tr>
                    <td><a href="{{ route('admin.marketing.campaigns.show', $c) }}" class="font-semibold text-brand-600 hover:underline">{{ $c->name }}</a></td>
                    <td class="text-xs capitalize">{{ str_replace('_', ' ', $c->provider) }}</td>
                    <td class="text-xs">{{ $c->objective ?? '—' }}</td>
                    <td><span class="status-pill capitalize {{ $statusPill[$c->status] ?? 'bg-slate-100' }}">{{ $c->status }}</span></td>
                    <td class="text-xs">{{ $c->daily_budget ? $inr($c->daily_budget) : '—' }}</td>
                    <td class="text-xs text-ink-500">{{ $c->created_at->diffForHumans() }}</td>
                    <td class="text-right"><a href="{{ route('admin.marketing.campaigns.show', $c) }}" class="btn-ghost btn-xs">View</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-ink-500">No campaigns yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $campaigns->links() }}</div>
@endsection

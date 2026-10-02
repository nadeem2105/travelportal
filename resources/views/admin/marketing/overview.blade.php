@extends('layouts.admin')
@section('pageTitle', 'Marketing Dashboard')

@php $inr = fn ($n) => '₹' . number_format((float) $n, 0); @endphp

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-xl font-bold">Marketing Dashboard</h1>
        <p class="text-xs text-ink-500">Advertising performance across connected platforms · last {{ $range }} days</p>
    </div>
    <div class="flex items-center gap-2">
        @foreach ([7, 30, 90] as $d)
            <a href="{{ route('admin.marketing.overview', ['days' => $d]) }}" class="rounded-full px-3 py-1 text-xs {{ $range === $d ? 'bg-brand-600 text-white' : 'bg-ink-100 text-ink-600' }}">{{ $d }}d</a>
        @endforeach
        <a href="{{ route('admin.marketing.accounts') }}" class="btn-ghost btn-sm">Ad Accounts</a>
    </div>
</div>

@if ($connectionCount === 0)
    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        No ad accounts connected yet. <a href="{{ route('admin.marketing.accounts') }}" class="font-semibold underline">Connect Google Ads or Meta Ads</a> to start syncing performance. Lead ingestion via webhooks already works independently.
    </div>
@endif

<div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
    @php
        $kpis = [
            ['Spend', $inr($totals->spend ?? 0)],
            ['Impressions', number_format($totals->impressions ?? 0)],
            ['Clicks', number_format($totals->clicks ?? 0)],
            ['Leads', number_format($totals->leads ?? 0)],
            ['CPL', ($totals->leads ?? 0) > 0 ? $inr(($totals->spend ?? 0) / $totals->leads) : '—'],
            ['Revenue', $inr($totals->revenue ?? 0)],
        ];
    @endphp
    @foreach ($kpis as [$label, $val])
        <div class="admin-card p-4">
            <div class="text-lg font-bold text-ink-800">{{ $val }}</div>
            <div class="text-[11px] text-ink-500">{{ $label }}</div>
        </div>
    @endforeach
</div>

<div class="admin-card mt-4 overflow-x-auto">
    <div class="flex items-center justify-between p-4">
        <h3 class="text-sm font-bold">Recent Campaigns</h3>
    </div>
    <table class="admin-table">
        <thead>
            <tr><th>Name</th><th>Platform</th><th>Objective</th><th>Status</th><th>Daily Budget</th><th>Created</th></tr>
        </thead>
        <tbody>
            @forelse ($campaigns as $c)
                <tr>
                    <td class="font-semibold">{{ $c->name }}</td>
                    <td class="text-xs capitalize">{{ str_replace('_', ' ', $c->provider) }}</td>
                    <td class="text-xs">{{ $c->objective ?? '—' }}</td>
                    <td><span class="status-pill capitalize bg-slate-100 text-slate-600">{{ $c->status }}</span></td>
                    <td class="text-xs">{{ $c->daily_budget ? $inr($c->daily_budget) : '—' }}</td>
                    <td class="text-xs text-ink-500">{{ $c->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-ink-500">No campaigns yet. Connect an account, then create your first campaign.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<p class="mt-4 text-[11px] text-ink-400">Note: performance figures come from synced platform metrics. Values shown are revenue, not profit. Campaigns are always created paused — spend never starts without explicit activation.</p>
@endsection

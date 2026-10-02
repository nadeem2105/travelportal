@extends('layouts.admin')
@section('pageTitle', 'CRM Analytics')

@php
    $inr = fn ($n) => '₹' . number_format((float) $n, 0);
    $barColors = ['#4b4bd6', '#0f9d58', '#f4b400', '#db4437', '#7b1fa2', '#00838f', '#5d4037', '#455a64'];
    // Reusable bar-list renderer.
    $maxOf = fn ($coll) => max(1, collect($coll)->max() ?? 1);
@endphp

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-xl font-bold">CRM Analytics</h1>
        <p class="text-xs text-ink-500">{{ $from->format('d M Y') }} — {{ $to->format('d M Y') }}</p>
    </div>
    <form method="GET" class="flex items-center gap-2">
        @foreach (['7d' => '7 days', '30d' => '30 days', '90d' => '90 days', 'ytd' => 'YTD', 'all' => 'All'] as $key => $label)
            <a href="{{ route('admin.crm-analytics.index', ['preset' => $key]) }}"
               class="rounded-full px-3 py-1 text-xs {{ $preset === $key ? 'bg-brand-600 text-white' : 'bg-ink-100 text-ink-600 hover:bg-ink-200' }}">{{ $label }}</a>
        @endforeach
    </form>
</div>

{{-- Headline KPIs --}}
<div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
    @php
        $kpis = [
            ['New Leads', $leadTotals['total'], 'text-brand-700'],
            ['Converted', $leadTotals['converted'], 'text-emerald-600'],
            ['Quotation Win Rate', $quotationStats['win_rate'] . '%', 'text-indigo-600'],
            ['Confirmed Revenue', $inr($revenue['confirmed_total']), 'text-emerald-700'],
        ];
    @endphp
    @foreach ($kpis as [$label, $val, $cls])
        <div class="admin-card p-4">
            <div class="text-2xl font-bold {{ $cls }}">{{ $val }}</div>
            <div class="text-xs text-ink-500">{{ $label }}</div>
        </div>
    @endforeach
</div>

{{-- Conversion funnel --}}
<div class="admin-card mt-4 p-5">
    <h3 class="mb-3 text-sm font-bold">Conversion Funnel</h3>
    @php
        $funnelSteps = [
            ['Leads', $funnel['leads'], '#4b4bd6'],
            ['Quoted', $funnel['quoted'], '#5c6bc0'],
            ['Accepted', $funnel['accepted'], '#26a69a'],
            ['Converted', $funnel['converted'], '#0f9d58'],
        ];
        $funnelMax = max(1, collect($funnelSteps)->max(fn ($s) => $s[1]));
    @endphp
    <div class="space-y-2">
        @foreach ($funnelSteps as [$label, $val, $color])
            <div class="flex items-center gap-3">
                <div class="w-24 shrink-0 text-xs text-ink-600">{{ $label }}</div>
                <div class="h-6 flex-1 rounded bg-ink-100">
                    <div class="flex h-6 items-center rounded px-2 text-[11px] font-semibold text-white" style="width: {{ max(6, round($val / $funnelMax * 100)) }}%; background: {{ $color }};">{{ $val }}</div>
                </div>
            </div>
        @endforeach
    </div>
    <p class="mt-3 text-xs text-ink-500">
        Lead → Quote: <strong>{{ $funnel['leads'] ? round($funnel['quoted'] / $funnel['leads'] * 100) : 0 }}%</strong>
        · Quote → Converted: <strong>{{ $funnel['quoted'] ? round($funnel['converted'] / $funnel['quoted'] * 100) : 0 }}%</strong>
    </p>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
    {{-- Leads by source --}}
    <div class="admin-card p-5">
        <h3 class="mb-3 text-sm font-bold">Leads by Source</h3>
        @include('admin.crm.analytics._bars', ['data' => $leadsBySource, 'colors' => $barColors])
    </div>

    {{-- Leads by stage --}}
    <div class="admin-card p-5">
        <h3 class="mb-3 text-sm font-bold">Leads by Pipeline Stage</h3>
        @include('admin.crm.analytics._bars', ['data' => $leadsByStage, 'colors' => $barColors])
    </div>

    {{-- Leads by status --}}
    <div class="admin-card p-5">
        <h3 class="mb-3 text-sm font-bold">Leads by Status</h3>
        @include('admin.crm.analytics._bars', ['data' => $leadsByStatus, 'colors' => $barColors])
    </div>

    {{-- Quotations --}}
    <div class="admin-card p-5">
        <h3 class="mb-3 text-sm font-bold">Quotations</h3>
        <div class="grid grid-cols-3 gap-2 text-center">
            <div><div class="text-lg font-bold text-ink-800">{{ $quotationStats['total'] }}</div><div class="text-[11px] text-ink-500">Total</div></div>
            <div><div class="text-lg font-bold text-emerald-600">{{ $quotationStats['won'] }}</div><div class="text-[11px] text-ink-500">Won</div></div>
            <div><div class="text-lg font-bold text-indigo-600">{{ $quotationStats['win_rate'] }}%</div><div class="text-[11px] text-ink-500">Win Rate</div></div>
        </div>
        <div class="mt-3 border-t pt-3 text-xs text-ink-600">
            Value sent: <strong>{{ $inr($quotationStats['value_sent']) }}</strong><br>
            Value won: <strong class="text-emerald-700">{{ $inr($quotationStats['value_won']) }}</strong>
        </div>
        <div class="mt-3">
            @include('admin.crm.analytics._bars', ['data' => $quotationStats['by_status'], 'colors' => $barColors])
        </div>
    </div>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
    {{-- Revenue --}}
    <div class="admin-card p-5">
        <h3 class="mb-3 text-sm font-bold">Revenue</h3>
        <div class="text-2xl font-bold text-emerald-700">{{ $inr($revenue['confirmed_total']) }}</div>
        <div class="text-xs text-ink-500">{{ $revenue['confirmed_count'] }} confirmed booking(s)</div>
        <div class="mt-3 border-t pt-3 text-xs text-ink-600">From converted quotations: <strong>{{ $inr($revenue['from_quotations']) }}</strong></div>
    </div>

    {{-- WhatsApp --}}
    <div class="admin-card p-5">
        <h3 class="mb-3 text-sm font-bold">WhatsApp Messages</h3>
        <div class="flex items-center justify-between py-1 text-sm"><span class="text-ink-500">Inbound</span><strong>{{ $whatsapp['inbound'] }}</strong></div>
        <div class="flex items-center justify-between py-1 text-sm"><span class="text-ink-500">Outbound</span><strong>{{ $whatsapp['outbound'] }}</strong></div>
        <div class="flex items-center justify-between py-1 text-sm"><span class="text-ink-500">Failed</span><strong class="text-rose-600">{{ $whatsapp['failed'] }}</strong></div>
    </div>

    {{-- Lead quality --}}
    <div class="admin-card p-5">
        <h3 class="mb-3 text-sm font-bold">Lead Pipeline</h3>
        <div class="flex items-center justify-between py-1 text-sm"><span class="text-ink-500">Open</span><strong>{{ $leadTotals['open'] }}</strong></div>
        <div class="flex items-center justify-between py-1 text-sm"><span class="text-ink-500">Converted</span><strong class="text-emerald-600">{{ $leadTotals['converted'] }}</strong></div>
        <div class="flex items-center justify-between py-1 text-sm"><span class="text-ink-500">Lost</span><strong class="text-rose-600">{{ $leadTotals['lost'] }}</strong></div>
        <div class="flex items-center justify-between py-1 text-sm"><span class="text-ink-500">Avg. score</span><strong>{{ $leadTotals['avg_score'] }}</strong></div>
    </div>
</div>

{{-- Agent performance --}}
<div class="admin-card mt-4 overflow-x-auto p-5">
    <h3 class="mb-3 text-sm font-bold">Agent Performance</h3>
    <table class="admin-table">
        <thead>
            <tr><th>Agent</th><th class="text-center">Leads</th><th class="text-center">Converted</th><th class="text-center">Conversion %</th></tr>
        </thead>
        <tbody>
            @forelse ($agents as $a)
                <tr>
                    <td class="font-semibold">{{ $a->agent ?? 'Unassigned' }}</td>
                    <td class="text-center">{{ $a->leads }}</td>
                    <td class="text-center text-emerald-600">{{ $a->converted }}</td>
                    <td class="text-center">{{ $a->leads ? round($a->converted / $a->leads * 100) : 0 }}%</td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-6 text-center text-ink-500">No assigned leads in this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

@extends('layouts.admin')
@section('pageTitle', 'Analytics')

@php
    $rangeLabels = [
        'today' => 'Today', 'yesterday' => 'Yesterday', 'last_7' => 'Last 7 days',
        'last_30' => 'Last 30 days', 'last_90' => 'Last 90 days',
        'this_month' => 'This month', 'prev_month' => 'Previous month', 'custom' => 'Custom',
    ];
    $delta = function ($k) use ($deltas) {
        $d = $deltas[$k] ?? 0;
        $cls = $d > 0 ? 'text-emerald-600' : ($d < 0 ? 'text-rose-600' : 'text-slate-400');
        $arrow = $d > 0 ? '▲' : ($d < 0 ? '▼' : '·');
        return '<span class="' . $cls . '">' . $arrow . ' ' . abs($d) . '%</span>';
    };
@endphp

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-xl font-bold">Website Analytics</h1>
        <p class="text-xs text-ink-500">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} · vs previous period</p>
    </div>
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <select name="range" onchange="this.form.submit()" class="input !w-auto text-sm">
            @foreach ($rangeLabels as $k => $label)
                <option value="{{ $k }}" @selected($range === $k)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from', $from->format('Y-m-d')) }}" class="input !w-auto text-sm">
        <input type="date" name="to" value="{{ request('to', $to->format('Y-m-d')) }}" class="input !w-auto text-sm">
        <button class="btn-ghost btn-sm" name="range" value="custom">Apply</button>
    </form>
</div>

@php
    $kpis = [
        ['Sessions', number_format($overview['sessions']), 'sessions'],
        ['Users', number_format($overview['users']), 'users'],
        ['New Users', number_format($overview['new_users']), 'new_users'],
        ['Returning', number_format($overview['returning_users']), 'returning_users'],
        ['Page Views', number_format($overview['page_views']), 'page_views'],
        ['Leads', number_format($overview['leads']), 'leads'],
        ['Bookings', number_format($overview['bookings']), 'bookings'],
        ['Revenue', money($overview['revenue']), 'revenue'],
        ['Conversion Rate', $overview['conversion_rate'] . '%', 'conversion_rate'],
        ['Avg Booking Value', money($overview['avg_booking_value']), 'avg_booking_value'],
    ];
@endphp
<div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
    @foreach ($kpis as [$label, $value, $key])
        <div class="admin-card p-4">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-400">{{ $label }}</p>
            <p class="mt-1 text-xl font-bold text-ink-900">{{ $value }}</p>
            <p class="mt-0.5 text-[11px]">{!! $delta($key) !!}</p>
        </div>
    @endforeach
</div>

{{-- Trends --}}
<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="admin-card p-5 lg:col-span-2">
        <h2 class="mb-3 text-sm font-bold text-ink-700">Traffic &amp; Bookings</h2>
        <div style="height:280px"><canvas id="trafficChart"></canvas></div>
    </div>
    <div class="admin-card p-5">
        <h2 class="mb-3 text-sm font-bold text-ink-700">Revenue</h2>
        <div style="height:280px"><canvas id="revenueChart"></canvas></div>
    </div>
</div>

{{-- Sources / devices / funnel --}}
<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="admin-card p-5">
        <h2 class="mb-3 text-sm font-bold text-ink-700">Traffic Sources</h2>
        <div style="height:220px"><canvas id="sourcesChart"></canvas></div>
    </div>
    <div class="admin-card p-5">
        <h2 class="mb-3 text-sm font-bold text-ink-700">Devices</h2>
        <div style="height:220px"><canvas id="devicesChart"></canvas></div>
    </div>
    <div class="admin-card p-5">
        <h2 class="mb-3 text-sm font-bold text-ink-700">Booking Funnel</h2>
        <div class="space-y-2">
            @php $maxF = max(1, $funnel[0]['count'] ?? 1); @endphp
            @foreach ($funnel as $step)
                <div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-ink-700">{{ $step['label'] }}</span>
                        <span class="text-ink-500">{{ number_format($step['count']) }}@if(!is_null($step['rate'])) · {{ $step['rate'] }}%@endif</span>
                    </div>
                    <div class="mt-1 h-2 rounded-full bg-slate-100">
                        <div class="h-2 rounded-full bg-brand-500" style="width: {{ min(100, round(($step['count'] / $maxF) * 100)) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Real-time --}}
<div class="admin-card mt-4 p-5" x-data="lzRealtime()" x-init="start()">
    <div class="flex items-center justify-between">
        <h2 class="text-sm font-bold text-ink-700">Real-time <span class="ml-1 inline-block h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span></h2>
        <span class="text-xs text-ink-500">Active now: <span class="font-bold text-ink-900" x-text="active"></span></span>
    </div>
    <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-ink-400">Current pages</p>
            <template x-for="p in pages" :key="p.path">
                <div class="flex justify-between border-b border-slate-100 py-1 text-xs"><span class="truncate text-ink-700" x-text="p.path"></span><span class="text-ink-500" x-text="p.count"></span></div>
            </template>
            <p x-show="!pages.length" class="py-2 text-xs text-ink-400">No active pages.</p>
        </div>
        <div>
            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-ink-400">Recent events</p>
            <template x-for="(e,i) in events" :key="i">
                <div class="flex justify-between border-b border-slate-100 py-1 text-xs"><span class="text-ink-700" x-text="e.event"></span><span class="text-ink-400" x-text="e.product || ''"></span></div>
            </template>
            <p x-show="!events.length" class="py-2 text-xs text-ink-400">No recent events.</p>
        </div>
    </div>
</div>

{{-- Tables --}}
<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
    <div class="admin-card p-5">
        <h2 class="mb-3 text-sm font-bold text-ink-700">Top Packages</h2>
        <div class="overflow-x-auto">
            <table class="admin-table text-sm">
                <thead><tr><th>Package</th><th class="text-center">Views</th><th class="text-center">Enq.</th><th class="text-center">Bkgs</th><th class="text-right">Revenue</th></tr></thead>
                <tbody>
                    @forelse ($topPackages as $p)
                        <tr><td class="max-w-[180px] truncate">{{ $p['name'] }}</td><td class="text-center">{{ $p['views'] }}</td><td class="text-center">{{ $p['enquiries'] }}</td><td class="text-center">{{ $p['bookings'] }}</td><td class="text-right">{{ money($p['revenue']) }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-center text-ink-400">No data yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="admin-card p-5">
        <h2 class="mb-3 text-sm font-bold text-ink-700">Top Pages</h2>
        <div class="overflow-x-auto">
            <table class="admin-table text-sm">
                <thead><tr><th>Path</th><th class="text-center">Views</th><th class="text-center">Sessions</th><th class="text-right">Avg time</th></tr></thead>
                <tbody>
                    @forelse ($topPages as $pg)
                        <tr><td class="max-w-[200px] truncate">{{ $pg['path'] }}</td><td class="text-center">{{ $pg['views'] }}</td><td class="text-center">{{ $pg['sessions'] }}</td><td class="text-right">{{ $pg['avg_time'] }}s</td></tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-ink-400">No data yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="admin-card p-5">
        <h2 class="mb-3 text-sm font-bold text-ink-700">Top Destinations</h2>
        <div class="overflow-x-auto">
            <table class="admin-table text-sm">
                <thead><tr><th>Destination</th><th class="text-right">Views</th></tr></thead>
                <tbody>
                    @forelse ($topDestinations as $d)
                        <tr><td>{{ $d['name'] }}</td><td class="text-right">{{ $d['views'] }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="py-4 text-center text-ink-400">No data yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="admin-card p-5">
        <h2 class="mb-3 text-sm font-bold text-ink-700">Campaigns</h2>
        <div class="overflow-x-auto">
            <table class="admin-table text-sm">
                <thead><tr><th>Campaign</th><th>Source</th><th class="text-center">Sess.</th><th class="text-center">Leads</th><th class="text-center">Bkgs</th><th class="text-right">Revenue</th></tr></thead>
                <tbody>
                    @forelse ($campaigns as $c)
                        <tr><td class="max-w-[140px] truncate">{{ $c['campaign'] }}</td><td>{{ $c['source'] }}</td><td class="text-center">{{ $c['sessions'] }}</td><td class="text-center">{{ $c['leads'] }}</td><td class="text-center">{{ $c['bookings'] }}</td><td class="text-right">{{ money($c['revenue']) }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="py-4 text-center text-ink-400">No campaign traffic yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<p class="mt-4 text-[11px] text-ink-400">
    Data is captured first-party (consent-gated) plus your bookings/leads. Configure GA4 / GTM / Meta Pixel / Google Ads in
    <a href="{{ route('admin.integrations.index') }}" class="text-brand-600 underline">Integrations</a>.
    Numbers populate once the migration has run and visitors accept analytics cookies.
</p>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    const series = @json($series);
    const sources = @json($sources);
    const devices = @json($devices['devices'] ?? []);
    const palette = ['#2563eb','#16a34a','#d97706','#db2777','#7c3aed','#0891b2','#64748b'];

    new Chart(document.getElementById('trafficChart'), {
        type: 'line',
        data: { labels: series.labels, datasets: [
            { label: 'Sessions', data: series.sessions, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.1)', fill: true, tension: .35 },
            { label: 'Bookings', data: series.bookings, borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,.08)', fill: true, tension: .35 },
        ]},
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: { labels: series.labels, datasets: [{ label: 'Revenue', data: series.revenue, backgroundColor: '#2563eb' }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('sourcesChart'), {
        type: 'doughnut',
        data: { labels: sources.map(s => s.channel), datasets: [{ data: sources.map(s => s.sessions), backgroundColor: palette }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    const dKeys = Object.keys(devices);
    new Chart(document.getElementById('devicesChart'), {
        type: 'doughnut',
        data: { labels: dKeys, datasets: [{ data: dKeys.map(k => devices[k]), backgroundColor: palette }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
});

function lzRealtime() {
    return {
        active: 0, pages: [], events: [], timer: null,
        start() { this.poll(); this.timer = setInterval(() => this.poll(), 15000); },
        async poll() {
            try {
                const r = await fetch(@json(route('admin.analytics.realtime')), { headers: { 'Accept': 'application/json' } });
                if (!r.ok) return;
                const d = await r.json();
                this.active = d.active_users || 0;
                this.pages = d.pages || [];
                this.events = d.events || [];
            } catch (e) {}
        },
    };
}
</script>
@endpush

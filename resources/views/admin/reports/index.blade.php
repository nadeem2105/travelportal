@extends('layouts.admin')
@section('pageTitle', 'Reports')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-xl font-bold">Reports</h1>
        <div class="flex flex-wrap gap-2">
            @foreach (['today' => 'Today', 'yesterday' => 'Yesterday', '7_days' => '7 Days', '30_days' => '30 Days', 'this_month' => 'This Month'] as $k => $label)
                <a href="{{ route('admin.reports.index', ['range' => $k]) }}"
                   class="rounded-full px-4 py-1.5 text-xs font-semibold {{ request('range', '30_days') === $k ? 'bg-brand-600 text-white' : 'bg-white text-ink-700 ring-1 ring-slate-200' }}">{{ $label }}</a>
            @endforeach
            <a href="{{ route('admin.reports.export', request()->only('range')) }}" class="btn-ghost btn-sm">Export Sales CSV</a>
            <a href="{{ route('admin.reports.financial-export', request()->only('range')) }}" class="btn-ghost btn-sm">Export Financial Ledger</a>
        </div>
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ([
            ['Bookings', number_format($summary['bookings'])],
            ['Revenue (confirmed)', money($summary['revenue'])],
            ['Taxes Collected', money($summary['taxes'])],
            ['Discounts Given', money($summary['discounts'])],
            ['Refunds Processed', money($summary['refunds'])],
            ['New Customers', number_format($summary['new_customers'])],
        ] as [$label, $value])
            <div class="admin-card">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-500">{{ $label }}</p>
                <p class="font-display mt-1 text-2xl font-extrabold text-ink-900">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    {{-- Financial Profitability & Merchant Ledger Card --}}
    <div class="admin-card mt-4 border-l-4 border-l-emerald-500">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-display text-base font-bold text-ink-900">Financial Profitability &amp; Merchant Ledger</h2>
                <p class="text-xs text-ink-500">Gross Transaction Volume (GTV), supplier payables, gateway costs and net platform margin</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">Profit Margin: {{ $financials['margin_percentage'] }}%</span>
                <a href="{{ route('admin.reports.financial-export', request()->only('range')) }}" class="btn-ghost btn-sm text-xs">Download Ledger CSV</a>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6 text-center">
            <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">Gross Volume</p>
                <p class="font-display mt-1 text-lg font-extrabold text-ink-900">{{ money($financials['gross_turnover']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">Supplier Cost</p>
                <p class="font-display mt-1 text-lg font-extrabold text-rose-600">{{ money($financials['supplier_payables']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">Gateway Fees (2%)</p>
                <p class="font-display mt-1 text-lg font-extrabold text-amber-600">{{ money($financials['gateway_fees']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">Taxes Collected</p>
                <p class="font-display mt-1 text-lg font-extrabold text-slate-700">{{ money($financials['taxes_collected']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">Refunds</p>
                <p class="font-display mt-1 text-lg font-extrabold text-red-500">{{ money($financials['refunds_deducted']) }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-800">Net Platform Margin</p>
                <p class="font-display mt-1 text-lg font-extrabold text-emerald-700">{{ money($financials['net_margin']) }}</p>
            </div>
        </div>
    </div>

    {{-- E-Commerce Conversion Funnel Card --}}
    <div class="admin-card mt-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-display text-base font-bold text-ink-900">E-Commerce Conversion Funnel</h2>
                <p class="text-xs text-ink-500">Visitor progression from search to paid booking confirmation</p>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <span class="rounded-full bg-emerald-50 px-3 py-1 font-bold text-emerald-700">Checkout Conv: {{ $funnel['checkout_conversion_rate'] }}%</span>
                <span class="rounded-full bg-brand-50 px-3 py-1 font-bold text-brand-700">Overall Conv: {{ $funnel['overall_conversion_rate'] }}%</span>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-3 text-center">
            <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                <p class="text-xs font-semibold text-ink-500 uppercase tracking-wider">1. Searches</p>
                <p class="font-display mt-1 text-2xl font-extrabold text-ink-900">{{ number_format($funnel['searches']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                <p class="text-xs font-semibold text-ink-500 uppercase tracking-wider">2. Product Views</p>
                <p class="font-display mt-1 text-2xl font-extrabold text-ink-900">{{ number_format($funnel['product_views']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                <p class="text-xs font-semibold text-ink-500 uppercase tracking-wider">3. Checkouts Started</p>
                <p class="font-display mt-1 text-2xl font-extrabold text-ink-900">{{ number_format($funnel['checkouts']) }}</p>
            </div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-3">
                <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wider">4. Bookings Confirmed</p>
                <p class="font-display mt-1 text-2xl font-extrabold text-emerald-600">{{ number_format($funnel['confirmed']) }}</p>
            </div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div class="admin-card overflow-x-auto">
            <h2 class="font-display text-base font-bold">Product-wise Sales</h2>
            <table class="admin-table mt-2">
                <thead><tr><th>Product</th><th>Bookings</th><th class="text-right">Revenue</th></tr></thead>
                <tbody>
                    @forelse ($productWise as $row)
                        <tr>
                            <td class="font-bold capitalize">{{ $row->product_type }}</td>
                            <td>{{ $row->count }}</td>
                            <td class="text-right font-bold">{{ money((float) $row->revenue) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-ink-500">No data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-card overflow-x-auto">
            <h2 class="font-display text-base font-bold">Daily Trend</h2>
            <table class="admin-table mt-2 max-h-64 overflow-y-auto">
                <thead><tr><th>Date</th><th>Bookings</th><th class="text-right">Revenue</th></tr></thead>
                <tbody>
                    @forelse ($daily as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row->date)->format('d M') }}</td>
                            <td>{{ $row->bookings }}</td>
                            <td class="text-right font-bold">{{ money((float) $row->revenue) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-ink-500">No data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-card overflow-x-auto">
            <h2 class="font-display text-base font-bold">Supplier Performance</h2>
            <table class="admin-table mt-2">
                <thead><tr><th>Supplier</th><th>Type</th><th class="text-right">Bookings</th></tr></thead>
                <tbody>
                    @forelse ($supplierPerformance as $supplier)
                        <tr>
                            <td class="font-bold">{{ $supplier->name }}</td>
                            <td class="capitalize">{{ $supplier->type }}</td>
                            <td class="text-right font-bold">{{ $supplier->bookings_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-ink-500">No suppliers</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-card overflow-x-auto">
            <h2 class="font-display text-base font-bold">Featured Destinations</h2>
            <table class="admin-table mt-2">
                <thead><tr><th>Destination</th><th>Packages</th></tr></thead>
                <tbody>
                    @foreach ($topDestinations as $destination)
                        <tr>
                            <td class="font-bold">{{ $destination->name }}</td>
                            <td>{{ $destination->packages_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

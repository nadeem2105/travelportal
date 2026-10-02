@extends('layouts.admin')
@section('pageTitle', 'Today — Trip Operations')

@php
    $tripPill = [
        'upcoming' => 'bg-slate-100 text-slate-600',
        'arriving_today' => 'bg-amber-100 text-amber-700',
        'in_progress' => 'bg-sky-100 text-sky-700',
        'completed' => 'bg-emerald-100 text-emerald-700',
        'cancelled' => 'bg-rose-100 text-rose-700',
    ];
    $kpiCards = [
        ['Arrivals Today', $kpis['arrivals_today'] ?? 0, 'bg-amber-50 text-amber-700 ring-amber-100', route('admin.trip-ops.upcoming', ['from' => now()->toDateString(), 'to' => now()->toDateString()])],
        ['Departures Today', $kpis['departures_today'] ?? 0, 'bg-indigo-50 text-indigo-700 ring-indigo-100', route('admin.trip-ops.active')],
        ['Active Trips', $kpis['active_trips'] ?? 0, 'bg-sky-50 text-sky-700 ring-sky-100', route('admin.trip-ops.active')],
        ['Arriving (7 days)', $kpis['upcoming_7d'] ?? 0, 'bg-emerald-50 text-emerald-700 ring-emerald-100', route('admin.trip-ops.upcoming')],
        ['Unassigned', $kpis['unassigned'] ?? 0, 'bg-rose-50 text-rose-700 ring-rose-100', route('admin.trip-ops.upcoming', ['assigned' => 'no'])],
    ];
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-xl font-bold">Today</h1>
            <p class="text-xs text-ink-500">{{ now()->format('l, d M Y') }} · Live operations overview</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.trip-ops.upcoming') }}" class="btn-ghost btn-sm">Upcoming</a>
            <a href="{{ route('admin.trip-ops.active') }}" class="btn-ghost btn-sm">Active</a>
            <a href="{{ route('admin.trip-ops.settings') }}" class="btn-ghost btn-sm">Settings</a>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($kpiCards as [$label, $value, $tone, $href])
            <a href="{{ $href }}" class="admin-card !p-4 ring-1 transition hover:-translate-y-0.5 hover:shadow-float {{ $tone }}">
                <p class="text-[11px] font-semibold uppercase tracking-wide opacity-80">{{ $label }}</p>
                <p class="mt-1 font-display text-2xl font-bold">{{ number_format($value) }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
        {{-- Arrivals today --}}
        <div class="admin-card">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-sm font-bold">Arrivals Today</h2>
                <span class="text-xs text-ink-500">{{ $arrivals->count() }} trip{{ $arrivals->count() === 1 ? '' : 's' }}</span>
            </div>

            <div class="mt-3 space-y-2">
                @forelse ($arrivals as $trip)
                    @php $driver = $trip->activeAssignments->first()?->driver; @endphp
                    <div class="rounded-xl border border-slate-100 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('admin.trip-ops.show', $trip) }}" class="font-semibold text-brand-600 hover:underline">{{ $trip->trip_reference }}</a>
                                <p class="truncate text-sm font-medium text-ink-800">{{ $trip->customerName() }}</p>
                                <p class="truncate text-xs text-ink-500">{{ $trip->destination_label ?: '—' }} · {{ $trip->total_days }} day{{ $trip->total_days === 1 ? '' : 's' }}</p>
                            </div>
                            <span class="status-pill shrink-0 {{ $tripPill[$trip->status] ?? 'bg-slate-100 text-slate-600' }}">{{ label_case($trip->status) }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                            @if ($driver)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-700">{{ $driver->name }}</span>
                                <span class="text-ink-500">{{ $driver->vehicleLabel() }}</span>
                            @else
                                <span class="rounded-full bg-rose-50 px-2 py-0.5 font-semibold text-rose-600">No driver assigned</span>
                            @endif
                            @if ($trip->customerPhone())
                                <a href="tel:{{ $trip->customerPhone() }}" class="ml-auto text-brand-600 hover:underline">{{ $trip->customerPhone() }}</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-ink-500">No arrivals scheduled for today.</p>
                @endforelse
            </div>
        </div>

        {{-- Live timeline --}}
        <div class="admin-card">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-sm font-bold">Today's Timeline</h2>
                <span class="text-xs text-ink-500">{{ $timeline->count() }} active</span>
            </div>

            <div class="mt-3 space-y-4">
                @forelse ($timeline as $trip)
                    <div>
                        <div class="flex items-center justify-between">
                            <a href="{{ route('admin.trip-ops.show', $trip) }}" class="text-sm font-semibold text-brand-600 hover:underline">{{ $trip->trip_reference }}</a>
                            <span class="text-xs text-ink-500">{{ $trip->customerName() }}</span>
                        </div>
                        <ol class="mt-2 space-y-1.5 border-l-2 border-slate-100 pl-3">
                            @foreach ($trip->events as $event)
                                <li class="relative">
                                    <span class="absolute -left-[17px] top-1.5 h-2 w-2 rounded-full bg-brand-500"></span>
                                    <div class="flex items-baseline gap-2 text-sm">
                                        <span class="w-16 shrink-0 font-mono text-xs text-ink-500">{{ $event->timeLabel() ?? '—' }}</span>
                                        <span class="font-medium text-ink-800">{{ $event->title }}</span>
                                    </div>
                                    <p class="pl-[72px] text-xs text-ink-500">
                                        {{ $event->typeLabel() }}@if ($event->location) · {{ $event->location }}@endif
                                    </p>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-ink-500">No timed events scheduled for today.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection

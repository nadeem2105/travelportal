@extends('layouts.admin')
@section('pageTitle', 'Driver Assignments')

@php
    $assignPill = [
        'assigned' => 'bg-emerald-100 text-emerald-700',
        'reassigned' => 'bg-amber-100 text-amber-700',
        'cancelled' => 'bg-rose-100 text-rose-700',
        'completed' => 'bg-slate-100 text-slate-500',
    ];
@endphp

@section('content')
<div x-data="{ reassignId: null, driversList: {{ Illuminate\Support\Js::from($drivers->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'vehicle' => $d->vehicleLabel()])) }} }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-xl font-bold">Driver Assignments</h1>
            <p class="text-xs text-ink-500">Active driver assignments across all trips</p>
        </div>
    </div>

    <x-admin.filters
        :action="route('admin.trip-ops.assignments.index')"
        search-placeholder="Search trip reference, customer, driver…"
        :count="$assignments->total()"
    />

    {{-- Desktop table --}}
    <div class="admin-card mt-4 hidden overflow-x-auto md:block">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Trip</th>
                    <th>Driver</th>
                    <th>Scope</th>
                    <th>Pickup</th>
                    <th>Status</th>
                    <th>Assigned</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assignments as $a)
                    <tr>
                        <td>
                            @if ($a->trip)
                                <a href="{{ route('admin.trip-ops.show', $a->trip) }}" class="font-bold text-brand-600 hover:underline">{{ $a->trip->trip_reference }}</a>
                                <div class="text-xs text-ink-500">{{ $a->trip->customerName() }}</div>
                            @else
                                <span class="text-ink-400">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="font-medium text-ink-800">{{ $a->driver?->name ?? '—' }}</div>
                            <div class="text-xs text-ink-500">{{ $a->driver?->vehicleLabel() }}</div>
                        </td>
                        <td class="text-xs">{{ $a->scopeLabel() }}</td>
                        <td class="text-xs">
                            {{ $a->pickup_location ?: '—' }}
                            @if ($a->pickup_datetime)<div class="text-ink-400">{{ $a->pickup_datetime->format('d M, h:i A') }}</div>@endif
                        </td>
                        <td><span class="status-pill {{ $assignPill[$a->status] ?? 'bg-slate-100 text-slate-600' }}">{{ label_case($a->status) }}</span></td>
                        <td class="text-xs text-ink-500">{{ optional($a->assigned_at)->format('d M Y, h:i A') ?? '—' }}</td>
                        <td class="text-right">
                            <div class="inline-flex items-center gap-1">
                                <button type="button" @click="reassignId = {{ $a->id }}" class="btn-ghost btn-sm">Reassign</button>
                                <form action="{{ route('admin.trip-ops.assignments.cancel', $a) }}" method="POST" class="inline" onsubmit="return confirm('Cancel this assignment?')">
                                    @csrf
                                    <button class="btn-ghost btn-sm !text-rose-600">Cancel</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-ink-500">No active assignments.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile cards --}}
    <div class="mt-4 space-y-3 md:hidden">
        @forelse ($assignments as $a)
            <div class="admin-card !p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        @if ($a->trip)
                            <a href="{{ route('admin.trip-ops.show', $a->trip) }}" class="font-bold text-brand-600 hover:underline">{{ $a->trip->trip_reference }}</a>
                        @endif
                        <p class="text-sm font-medium text-ink-800">{{ $a->driver?->name ?? '—' }}</p>
                    </div>
                    <span class="status-pill shrink-0 {{ $assignPill[$a->status] ?? 'bg-slate-100 text-slate-600' }}">{{ label_case($a->status) }}</span>
                </div>
                <p class="mt-1 text-xs text-ink-500">{{ $a->scopeLabel() }}</p>
                <p class="text-xs text-ink-500">{{ $a->driver?->vehicleLabel() }}</p>
                <div class="mt-3 flex flex-wrap gap-1">
                    <button type="button" @click="reassignId = {{ $a->id }}" class="btn-ghost btn-sm">Reassign</button>
                    <form action="{{ route('admin.trip-ops.assignments.cancel', $a) }}" method="POST" class="inline" onsubmit="return confirm('Cancel this assignment?')">
                        @csrf
                        <button class="btn-ghost btn-sm !text-rose-600">Cancel</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="admin-card py-6 text-center text-sm text-ink-500">No active assignments.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $assignments->links() }}</div>

    {{-- Reassign modal --}}
    <div x-show="reassignId !== null" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:p-8" @keydown.escape.window="reassignId = null">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-xl" @click.outside="reassignId = null">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="font-display text-lg font-bold">Reassign Driver</h2>
                <button type="button" @click="reassignId = null" class="text-ink-400 hover:text-ink-700">&times;</button>
            </div>
            <form :action="'{{ url('admin/trip-ops/assignments') }}/' + reassignId + '/reassign'" method="POST" class="px-6 py-5">
                @csrf
                <div>
                    <label class="label">New Driver *</label>
                    <select name="driver_id" required class="input">
                        <option value="">Select driver…</option>
                        <template x-for="d in driversList" :key="d.id">
                            <option :value="d.id" x-text="d.name + (d.vehicle && d.vehicle !== '—' ? ' — ' + d.vehicle : '')"></option>
                        </template>
                    </select>
                </div>
                <div class="mt-4">
                    <label class="label">Reason</label>
                    <input type="text" name="reason" class="input" placeholder="Why is this being reassigned?">
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t pt-4">
                    <button type="button" @click="reassignId = null" class="btn-ghost btn-sm">Cancel</button>
                    <button type="submit" class="btn-primary btn-sm">Reassign</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

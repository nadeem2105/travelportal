@extends('layouts.admin')
@section('pageTitle', $title)

@php
    $tripPill = [
        'upcoming' => 'bg-slate-100 text-slate-600',
        'arriving_today' => 'bg-amber-100 text-amber-700',
        'in_progress' => 'bg-sky-100 text-sky-700',
        'completed' => 'bg-emerald-100 text-emerald-700',
        'cancelled' => 'bg-rose-100 text-rose-700',
    ];
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-xl font-bold">{{ $title }}</h1>
            <p class="text-xs text-ink-500">{{ $trips->total() }} trip{{ $trips->total() === 1 ? '' : 's' }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.trip-ops.export', request()->only(['q', 'from', 'to', 'assigned', 'product_type'])) }}" class="btn-ghost btn-sm">Export CSV</a>
        </div>
    </div>

    <x-admin.filters
        :action="route('admin.' . $listRoute)"
        search-placeholder="Search reference, name, phone, destination…"
        :filters="[
            ['name' => 'product_type', 'label' => 'Product', 'all' => 'All products', 'options' => ['package' => 'Package', 'hotel' => 'Hotel', 'cab' => 'Cab', 'flight' => 'Flight']],
            ['name' => 'assigned', 'label' => 'Driver', 'all' => 'Any driver status', 'options' => ['yes' => 'Driver assigned', 'no' => 'Unassigned']],
        ]"
        :count="$trips->total()">
        <input type="date" name="from" value="{{ request('from') }}" class="input !w-auto" title="Arrival from">
        <input type="date" name="to" value="{{ request('to') }}" class="input !w-auto" title="Arrival to">
    </x-admin.filters>

    {{-- Desktop table --}}
    <div class="admin-card mt-4 hidden overflow-x-auto md:block">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Customer</th>
                    <th>Destination</th>
                    <th>Arrival</th>
                    <th>Departure</th>
                    <th class="text-center">Days</th>
                    <th>Status</th>
                    <th>Driver</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($trips as $trip)
                    @php $driver = $trip->activeAssignments->first()?->driver; @endphp
                    <tr>
                        <td><a href="{{ route('admin.trip-ops.show', $trip) }}" class="font-bold text-brand-600 hover:text-brand-800">{{ $trip->trip_reference }}</a></td>
                        <td>
                            <div class="font-medium text-ink-800">{{ $trip->customerName() }}</div>
                            <div class="text-xs text-ink-500">{{ $trip->customerPhone() ?? '—' }}</div>
                        </td>
                        <td class="text-sm">{{ $trip->destination_label ?: '—' }}</td>
                        <td class="text-xs">{{ optional($trip->arrival_date)->format('d M Y') ?? '—' }}</td>
                        <td class="text-xs">{{ optional($trip->departure_date)->format('d M Y') ?? '—' }}</td>
                        <td class="text-center">{{ $trip->total_days }}</td>
                        <td>
                            <form action="{{ route('admin.trip-ops.status', $trip) }}" method="POST" class="inline">
                                @csrf
                                <select name="status" onchange="this.form.submit()"
                                        class="status-pill cursor-pointer border-0 py-1 pl-2 pr-6 text-xs font-semibold focus:ring-2 focus:ring-brand-400 {{ $tripPill[$trip->status] ?? 'bg-slate-100 text-slate-600' }}"
                                        title="Change status">
                                    @foreach (\App\Models\Trip::STATUSES as $s)
                                        <option value="{{ $s }}" @selected($trip->status === $s)>{{ label_case($s) }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td>
                            @if ($driver)
                                <span class="text-sm font-medium text-ink-800">{{ $driver->name }}</span>
                            @else
                                <span class="status-pill bg-rose-50 text-rose-600">Unassigned</span>
                            @endif
                        </td>
                        <td class="text-right"><a href="{{ route('admin.trip-ops.show', $trip) }}" class="btn-ghost btn-sm">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-6 text-center text-ink-500">No trips found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile cards --}}
    <div class="mt-4 space-y-3 md:hidden">
        @forelse ($trips as $trip)
            @php $driver = $trip->activeAssignments->first()?->driver; @endphp
            <a href="{{ route('admin.trip-ops.show', $trip) }}" class="admin-card block !p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-bold text-brand-600">{{ $trip->trip_reference }}</p>
                        <p class="truncate text-sm font-medium text-ink-800">{{ $trip->customerName() }}</p>
                    </div>
                    <span class="status-pill shrink-0 {{ $tripPill[$trip->status] ?? 'bg-slate-100 text-slate-600' }}">{{ label_case($trip->status) }}</span>
                </div>
                <p class="mt-1 truncate text-xs text-ink-500">{{ $trip->destination_label ?: '—' }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-600">
                    <span>Arr: {{ optional($trip->arrival_date)->format('d M') ?? '—' }}</span>
                    <span>Dep: {{ optional($trip->departure_date)->format('d M') ?? '—' }}</span>
                    <span>{{ $trip->total_days }} day{{ $trip->total_days === 1 ? '' : 's' }}</span>
                </div>
                <div class="mt-2 text-xs">
                    @if ($driver)
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-700">{{ $driver->name }}</span>
                    @else
                        <span class="rounded-full bg-rose-50 px-2 py-0.5 font-semibold text-rose-600">Unassigned</span>
                    @endif
                </div>
            </a>
            <form action="{{ route('admin.trip-ops.status', $trip) }}" method="POST" class="-mt-2 px-4 pb-3">
                @csrf
                <label class="text-[11px] font-medium text-ink-500">Status</label>
                <select name="status" onchange="this.form.submit()" class="input mt-1 !py-1.5 text-xs">
                    @foreach (\App\Models\Trip::STATUSES as $s)
                        <option value="{{ $s }}" @selected($trip->status === $s)>{{ label_case($s) }}</option>
                    @endforeach
                </select>
            </form>
        @empty
            <p class="admin-card py-6 text-center text-sm text-ink-500">No trips found.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $trips->links() }}</div>
@endsection

@extends('layouts.admin')
@section('pageTitle', 'Trip ' . $trip->trip_reference)

@php
    $tripPill = [
        'upcoming' => 'bg-slate-100 text-slate-600',
        'arriving_today' => 'bg-amber-100 text-amber-700',
        'in_progress' => 'bg-sky-100 text-sky-700',
        'completed' => 'bg-emerald-100 text-emerald-700',
        'cancelled' => 'bg-rose-100 text-rose-700',
    ];
    $assignPill = [
        'assigned' => 'bg-emerald-100 text-emerald-700',
        'reassigned' => 'bg-amber-100 text-amber-700',
        'cancelled' => 'bg-rose-100 text-rose-700',
        'completed' => 'bg-slate-100 text-slate-500',
    ];
    $commPill = [
        'sent' => 'bg-emerald-100 text-emerald-700',
        'skipped' => 'bg-slate-100 text-slate-500',
        'failed' => 'bg-rose-100 text-rose-700',
    ];
    $activeAssignments = $trip->assignments->whereIn('status', ['assigned', 'reassigned']);
@endphp

@section('content')
<div x-data="{ assignOpen: false, reassignId: null, scope: 'entire_trip' }">
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.trip-ops.upcoming') }}" class="text-ink-400 hover:text-ink-700">&larr;</a>
                <h1 class="font-display text-xl font-bold">{{ $trip->trip_reference }}</h1>
                <span class="status-pill {{ $tripPill[$trip->status] ?? 'bg-slate-100 text-slate-600' }}">{{ label_case($trip->status) }}</span>
            </div>
            <p class="mt-1 text-xs text-ink-500">
                {{ $trip->destination_label ?: '—' }} · {{ $trip->total_days }} day{{ $trip->total_days === 1 ? '' : 's' }}
                · itinerary v{{ $trip->itinerary_version }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.trip-ops.driver-sheet', $trip) }}" target="_blank" class="btn-ghost btn-sm">Driver Sheet PDF</a>
            <a href="{{ route('admin.trip-ops.itinerary', $trip) }}" target="_blank" class="btn-ghost btn-sm">Itinerary PDF</a>
            <form action="{{ route('admin.trip-ops.send-itinerary', $trip) }}" method="POST" class="inline">
                @csrf
                <button class="btn-ghost btn-sm">Send Itinerary</button>
            </form>
            <form action="{{ route('admin.trip-ops.send-driver-reminder', $trip) }}" method="POST" class="inline" onsubmit="return confirm('Send a pickup reminder to the assigned driver(s) now?')">
                @csrf
                <button class="btn-ghost btn-sm">Send Driver Reminder</button>
            </form>
            <form action="{{ route('admin.trip-ops.send-driver-sheet', $trip) }}" method="POST" class="inline" onsubmit="return confirm('Send the driver sheet PDF to the assigned driver(s) on WhatsApp now?')">
                @csrf
                <button class="btn-ghost btn-sm">Send Driver Sheet</button>
            </form>
            <form action="{{ route('admin.trip-ops.send-tomorrow-plan', $trip) }}" method="POST" class="inline" onsubmit="return confirm(&quot;Send the customer tomorrow's plan now?&quot;)">
                @csrf
                <button class="btn-ghost btn-sm">Send Tomorrow's Plan</button>
            </form>
            <form action="{{ route('admin.trip-ops.regenerate', $trip) }}" method="POST" class="inline" onsubmit="return confirm('Rebuild itinerary from the booking? This bumps the version.')">
                @csrf
                <button class="btn-ghost btn-sm">Regenerate</button>
            </form>
            <button type="button" @click="assignOpen = true" class="btn-primary btn-sm">+ Assign Driver</button>
        </div>
    </div>

    <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-3">
        {{-- Left: customer + booking --}}
        <div class="space-y-5 lg:col-span-1">
            <div class="admin-card">
                <h2 class="font-display text-sm font-bold">Customer</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Name</dt><dd class="text-right font-medium text-ink-800">{{ $trip->customerName() }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Phone</dt><dd class="text-right">@if ($trip->customerPhone())<a href="tel:{{ $trip->customerPhone() }}" class="text-brand-600 hover:underline">{{ $trip->customerPhone() }}</a>@else — @endif</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Email</dt><dd class="truncate text-right">{{ $trip->customerEmail() ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="admin-card">
                <h2 class="font-display text-sm font-bold">Booking &amp; Dates</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Reference</dt><dd class="text-right">
                        @if ($trip->booking)<a href="{{ route('admin.bookings.show', $trip->booking) }}" class="font-semibold text-brand-600 hover:underline">{{ $trip->booking->booking_reference }}</a>@else — @endif
                    </dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Product</dt><dd class="text-right capitalize">{{ $trip->product_type ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Arrival</dt><dd class="text-right">{{ optional($trip->arrival_date)->format('d M Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Departure</dt><dd class="text-right">{{ optional($trip->departure_date)->format('d M Y') ?? '—' }}</dd></div>
                </dl>
            </div>

            {{-- Internal notes --}}
            <div class="admin-card">
                <h2 class="font-display text-sm font-bold">Internal Notes</h2>
                <form action="{{ route('admin.trip-ops.notes', $trip) }}" method="POST" class="mt-3">
                    @csrf
                    <textarea name="notes" rows="4" class="input" placeholder="Operational notes (not shown to customers)…">{{ $trip->notes }}</textarea>
                    <div class="mt-2 flex justify-end"><button class="btn-primary btn-sm">Save Notes</button></div>
                </form>
            </div>
        </div>

        {{-- Right: assignments + timeline --}}
        <div class="space-y-5 lg:col-span-2">
            {{-- Active driver assignments --}}
            <div class="admin-card">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-sm font-bold">Driver Assignments</h2>
                    <span class="text-xs text-ink-500">{{ $activeAssignments->count() }} active</span>
                </div>
                <div class="mt-3 space-y-3">
                    @forelse ($activeAssignments as $a)
                        <div class="rounded-xl border border-slate-100 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-ink-800">{{ $a->driver?->name ?? '—' }}</p>
                                    <p class="text-xs text-ink-500">{{ $a->driver?->vehicleLabel() }}@if ($a->driver?->phone) · <a href="tel:{{ $a->driver->phone }}" class="text-brand-600 hover:underline">{{ $a->driver->phone }}</a>@endif</p>
                                    <p class="mt-1 text-xs font-medium text-ink-600">{{ $a->scopeLabel() }}</p>
                                    @if ($a->pickup_location || $a->drop_location)
                                        <p class="text-xs text-ink-500">{{ $a->pickup_location ?: '—' }} → {{ $a->drop_location ?: '—' }}</p>
                                    @endif
                                    @if ($a->pickup_datetime)<p class="text-xs text-ink-500">Pickup: {{ $a->pickup_datetime->format('d M, h:i A') }}</p>@endif
                                    @if ($a->notes)<p class="mt-1 text-xs italic text-ink-500">{{ $a->notes }}</p>@endif
                                </div>
                                <span class="status-pill shrink-0 {{ $assignPill[$a->status] ?? 'bg-slate-100 text-slate-600' }}">{{ label_case($a->status) }}</span>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-1">
                                <button type="button" @click="reassignId = {{ $a->id }}" class="btn-ghost btn-sm">Reassign</button>
                                <form action="{{ route('admin.trip-ops.assignments.cancel', $a) }}" method="POST" class="inline" onsubmit="return confirm('Cancel this assignment?')">
                                    @csrf
                                    <button class="btn-ghost btn-sm !text-rose-600">Cancel</button>
                                </form>
                            </div>
                            @if ($a->histories->isNotEmpty())
                                <details class="mt-2 text-xs">
                                    <summary class="cursor-pointer text-ink-500">History ({{ $a->histories->count() }})</summary>
                                    <ul class="mt-1 space-y-1 border-l-2 border-slate-100 pl-3">
                                        @foreach ($a->histories as $h)
                                            <li class="text-ink-500">Reassigned · {{ optional($h->created_at)->format('d M, h:i A') }}@if ($h->reason) — {{ $h->reason }}@endif</li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        </div>
                    @empty
                        <p class="py-4 text-center text-sm text-ink-500">No driver assigned yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Day-by-day timeline --}}
            <div class="admin-card">
                <h2 class="font-display text-sm font-bold">Itinerary Timeline</h2>
                <div class="mt-3 space-y-5">
                    @forelse ($trip->days as $day)
                        <div>
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <h3 class="text-sm font-bold text-ink-800">
                                    Day {{ $day->day_number }}@if ($day->title) · {{ $day->title }}@endif
                                </h3>
                                <span class="text-xs text-ink-500">{{ optional($day->date)->format('d M Y') }}</span>
                            </div>
                            @if ($day->summary)<p class="mt-0.5 text-xs text-ink-500">{{ $day->summary }}</p>@endif
                            @if ($day->hotel_snapshot || $day->meals)
                                <p class="mt-0.5 text-xs text-ink-500">
                                    @if ($day->hotel_snapshot)🏨 {{ $day->hotel_snapshot }}@endif
                                    @if ($day->meals) · Meals: {{ $day->meals }}@endif
                                </p>
                            @endif
                            <ol class="mt-2 space-y-2 border-l-2 border-slate-100 pl-3">
                                @forelse ($day->events as $event)
                                    <li class="relative">
                                        <span class="absolute -left-[17px] top-1.5 h-2 w-2 rounded-full {{ $event->isCustomerVisible() ? 'bg-brand-500' : 'bg-slate-300' }}"></span>
                                        <div class="flex flex-wrap items-baseline gap-2 text-sm">
                                            <span class="w-16 shrink-0 font-mono text-xs text-ink-500">{{ $event->timeLabel() ?? '—' }}</span>
                                            <span class="font-medium text-ink-800">{{ $event->title }}</span>
                                            <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">{{ $event->typeLabel() }}</span>
                                            @unless ($event->isCustomerVisible())
                                                <span class="rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">Internal</span>
                                            @endunless
                                        </div>
                                        @if ($event->location || $event->description)
                                            <p class="pl-[72px] text-xs text-ink-500">
                                                @if ($event->location)
                                                    @if ($event->mapsLink())<a href="{{ $event->mapsLink() }}" target="_blank" class="text-brand-600 hover:underline">{{ $event->location }}</a>@else{{ $event->location }}@endif
                                                @endif
                                                @if ($event->description) · {{ $event->description }}@endif
                                            </p>
                                        @endif
                                    </li>
                                @empty
                                    <li class="text-xs text-ink-400">No events for this day.</li>
                                @endforelse
                            </ol>
                        </div>
                    @empty
                        <p class="py-4 text-center text-sm text-ink-500">No itinerary generated yet. Use "Regenerate" to build it from the booking.</p>
                    @endforelse
                </div>
            </div>

            {{-- Communications log --}}
            <div class="admin-card">
                <h2 class="font-display text-sm font-bold">Communications</h2>
                <div class="mt-3 space-y-2">
                    @forelse ($trip->comms->sortByDesc('created_at') as $comm)
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 px-3 py-2 text-xs">
                            <div class="min-w-0">
                                <span class="font-semibold text-ink-800">{{ label_case($comm->event_key) }}</span>
                                <span class="text-ink-500">· {{ strtoupper($comm->channel) }} → {{ $comm->recipient }}</span>
                                @if ($comm->error)<p class="truncate text-rose-500">{{ $comm->error }}</p>@endif
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="text-ink-400">{{ optional($comm->sent_at ?? $comm->created_at)->format('d M, h:i A') }}</span>
                                <span class="status-pill {{ $commPill[$comm->status] ?? 'bg-slate-100 text-slate-600' }}">{{ label_case($comm->status) }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="py-4 text-center text-sm text-ink-500">No communications sent yet.</p>
                    @endforelse
                </div>
            </div>

        </div>
    </div>

    {{-- Assign driver modal --}}
    <div x-show="assignOpen" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:p-8" @keydown.escape.window="assignOpen = false">
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl" @click.outside="assignOpen = false">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="font-display text-lg font-bold">Assign Driver</h2>
                <button type="button" @click="assignOpen = false" class="text-ink-400 hover:text-ink-700">&times;</button>
            </div>
            <form action="{{ route('admin.trip-ops.assign', $trip) }}" method="POST" class="px-6 py-5">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label">Driver *</label>
                        <select name="driver_id" required class="input">
                            <option value="">Select driver…</option>
                            @foreach ($drivers as $d)
                                <option value="{{ $d->id }}">{{ $d->name }}@if ($d->vehicleLabel() !== '—') — {{ $d->vehicleLabel() }}@endif</option>
                            @endforeach
                        </select>
                        @if ($drivers->isEmpty())<p class="mt-1 text-xs text-rose-500">No active drivers. <a href="{{ route('admin.trip-ops.drivers.index') }}" class="underline">Add one first.</a></p>@endif
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Scope *</label>
                        <select name="scope" x-model="scope" required class="input">
                            <option value="entire_trip">Entire Trip</option>
                            <option value="day">Specific Day</option>
                            <option value="transfer">Single Transfer</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2" x-show="scope === 'day'" x-cloak>
                        <label class="label">Day</label>
                        <select name="trip_day_id" class="input">
                            <option value="">Select day…</option>
                            @foreach ($trip->days as $day)
                                <option value="{{ $day->id }}">Day {{ $day->day_number }}@if ($day->title) — {{ $day->title }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2" x-show="scope === 'transfer'" x-cloak>
                        <label class="label">Transfer / Event</label>
                        <select name="trip_event_id" class="input">
                            <option value="">Select event…</option>
                            @foreach ($trip->days as $day)
                                @foreach ($day->events as $event)
                                    <option value="{{ $event->id }}">Day {{ $day->day_number }} · {{ $event->timeLabel() }} {{ $event->title }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Pickup Location</label>
                        <input type="text" name="pickup_location" class="input">
                    </div>
                    <div>
                        <label class="label">Drop Location</label>
                        <input type="text" name="drop_location" class="input">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Pickup Date &amp; Time</label>
                        <input type="datetime-local" name="pickup_datetime" class="input">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Notes for driver</label>
                        <textarea name="notes" rows="2" class="input"></textarea>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm sm:col-span-2">
                        <input type="checkbox" name="notify" value="1" checked class="rounded"> Notify driver &amp; customer now
                    </label>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t pt-4">
                    <button type="button" @click="assignOpen = false" class="btn-ghost btn-sm">Cancel</button>
                    <button type="submit" class="btn-primary btn-sm">Assign Driver</button>
                </div>
            </form>
        </div>
    </div>

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
                        @foreach ($drivers as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}@if ($d->vehicleLabel() !== '—') — {{ $d->vehicleLabel() }}@endif</option>
                        @endforeach
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

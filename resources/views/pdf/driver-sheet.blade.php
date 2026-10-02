@extends('pdf.layout')

@section('pdf_title', 'Driver Sheet — ' . $trip->trip_reference)

@section('doc_badge')
    <div class="doc-label">DRIVER SHEET</div>
    <div class="doc-big">{{ $trip->trip_reference }}</div>
@endsection

@section('doc_right')
    <div class="doc-line">Arrival: <strong>{{ optional($trip->arrival_date)->format('d M Y') ?? '—' }}</strong></div>
    <div class="doc-line">Days: <strong>{{ $trip->total_days }}</strong></div>
@endsection

@section('pdf_content')
    @include('pdf.partials.brand-header')

    <h2 class="sec">Trip & Customer</h2>
    <div class="card">
        <table class="w">
            <tr>
                <td class="vtop" style="width:50%;">
                    <div class="lbl">Customer</div>
                    <div class="val">{{ $trip->customerName() ?: '—' }}</div>
                    <div class="muted">{{ $trip->customerPhone() ?: 'no phone on file' }}</div>
                </td>
                <td class="vtop" style="width:50%;">
                    <div class="lbl">Destination</div>
                    <div class="val">{{ $trip->destination_label ?: '—' }}</div>
                    <div class="muted">
                        {{ optional($trip->arrival_date)->format('d M Y') ?? '—' }}
                        @if($trip->departure_date) &rarr; {{ $trip->departure_date->format('d M Y') }} @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <h2 class="sec">Driver Assignment(s)</h2>
    @forelse($assignments as $a)
        <div class="card">
            <table class="w">
                <tr>
                    <td class="vtop" style="width:34%;">
                        <div class="lbl">Driver</div>
                        <div class="val">{{ $a->driver?->name ?: '—' }}</div>
                        <div class="muted">{{ $a->driver?->phone ?: '' }}@if($a->driver?->alt_phone) / {{ $a->driver->alt_phone }}@endif</div>
                    </td>
                    <td class="vtop" style="width:33%;">
                        <div class="lbl">Vehicle</div>
                        <div class="val">{{ $a->driver?->vehicleLabel() ?: ($a->driver?->vehicle_number ?: '—') }}</div>
                        <div class="muted">{{ $a->driver?->vendor_name ?: '' }}</div>
                    </td>
                    <td class="vtop" style="width:33%;">
                        <div class="lbl">Scope</div>
                        <div class="val">{{ $a->scopeLabel() }}</div>
                        @if($a->pickup_datetime)<div class="muted">{{ $a->pickup_datetime->format('d M Y, h:i A') }}</div>@endif
                    </td>
                </tr>
                @if($a->pickup_location || $a->drop_location)
                    <tr>
                        <td class="vtop" colspan="3" style="padding-top:8px;">
                            <div class="lbl">Route</div>
                            <div class="val">{{ $a->pickup_location ?: 'Pickup' }} &rarr; {{ $a->drop_location ?: 'Drop' }}</div>
                        </td>
                    </tr>
                @endif
                @if($a->notes)
                    <tr><td colspan="3" style="padding-top:8px;"><div class="lbl">Notes</div><div class="muted">{{ $a->notes }}</div></td></tr>
                @endif
            </table>
        </div>
    @empty
        <div class="card"><div class="muted">No active driver assignment for this trip.</div></div>
    @endforelse

    <h2 class="sec">Day-by-Day Plan</h2>
    @foreach($trip->days->sortBy('day_number') as $day)
        <div class="card">
            <table class="w">
                <tr>
                    <td class="vtop">
                        <span class="pill">Day {{ $day->day_number }}</span>
                        <strong style="margin-left:6px;">{{ $day->title ?: 'Day ' . $day->day_number }}</strong>
                    </td>
                    <td class="vtop doc-right muted">{{ optional($day->date)->format('D, d M Y') }}</td>
                </tr>
            </table>
            @if($day->hotel_snapshot)<div class="muted" style="margin-top:6px;">Overnight: <strong>{{ $day->hotel_snapshot }}</strong></div>@endif
            @if($day->meals)<div class="muted">Meals: {{ $day->meals }}</div>@endif

            @if($day->events->count())
                <table class="data" style="margin-top:8px; table-layout:fixed;">
                    <thead><tr><th style="width:60px;">Time</th><th style="width:90px;">Type</th><th style="width:48%;">Detail</th><th>Location</th></tr></thead>
                    <tbody>
                        @foreach($day->events->sortBy('sort_order') as $ev)
                            <tr>
                                <td>{{ $ev->timeLabel() ?: '—' }}</td>
                                <td>{{ $ev->typeLabel() }}</td>
                                <td>
                                    {{ $ev->title }}
                                    @if($ev->description)<div class="muted">{{ $ev->description }}</div>@endif
                                </td>
                                <td>
                                    {{ $ev->location ?: '—' }}
                                    @if($ev->mapsLink())<div class="blue" style="font-size:9px;">{{ $ev->mapsLink() }}</div>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

    @if($trip->notes)
        <h2 class="sec">Internal Notes</h2>
        <div class="card"><div class="muted">{{ $trip->notes }}</div></div>
    @endif
@endsection

@section('foot_path', ' · Driver Sheet ' . $trip->trip_reference)

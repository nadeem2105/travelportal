@extends('pdf.layout')

@section('pdf_title', 'Your Itinerary — ' . ($trip->destination_label ?: $trip->trip_reference))

@section('doc_badge')
    <div class="doc-label blue">TRIP ITINERARY</div>
    <div class="doc-big">{{ $trip->destination_label ?: $trip->trip_reference }}</div>
@endsection

@section('doc_right')
    <div class="doc-line">Ref: <strong>{{ $trip->trip_reference }}</strong></div>
    <div class="doc-line">{{ optional($trip->arrival_date)->format('d M Y') ?? '' }}
        @if($trip->departure_date) &rarr; {{ $trip->departure_date->format('d M Y') }} @endif</div>
@endsection

@section('pdf_content')
    @include('pdf.partials.brand-header')

    <h2 class="sec">Trip Summary</h2>
    <div class="card">
        <table class="w">
            <tr>
                <td class="vtop" style="width:33%;"><div class="lbl">Guest</div><div class="val">{{ $trip->customerName() ?: 'Valued Guest' }}</div></td>
                <td class="vtop" style="width:33%;"><div class="lbl">Destination</div><div class="val">{{ $trip->destination_label ?: '—' }}</div></td>
                <td class="vtop" style="width:34%;"><div class="lbl">Duration</div><div class="val">{{ $trip->total_days }} day(s)</div></td>
            </tr>
        </table>
    </div>

    <h2 class="sec">Your Day-by-Day Plan</h2>
    @foreach($trip->days->sortBy('day_number') as $day)
        @php($events = $day->events->filter(fn ($e) => $e->isCustomerVisible())->sortBy('sort_order'))
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

            @if($day->summary)<div class="muted" style="margin-top:6px;">{{ $day->summary }}</div>@endif

            @if($events->count())
                <table class="data" style="margin-top:8px;">
                    <tbody>
                        @foreach($events as $ev)
                            <tr>
                                <td style="width:70px;">{{ $ev->timeLabel() ?: '' }}</td>
                                <td>
                                    <strong>{{ $ev->title }}</strong>
                                    @if($ev->description)<div class="muted">{{ $ev->description }}</div>@endif
                                    @if($ev->location)<div class="muted">{{ $ev->location }}</div>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if($day->hotel_snapshot)<div class="muted" style="margin-top:8px;">Overnight stay: <strong>{{ $day->hotel_snapshot }}</strong></div>@endif
            @if($day->meals)<div class="muted">Meals included: {{ $day->meals }}</div>@endif
        </div>
    @endforeach

    <div class="terms">
        We wish you a wonderful trip! For any assistance during your journey, please contact our helpline
        {{ settings('company_phone', '') }}. Timings are indicative and may adjust to local conditions.
    </div>
@endsection

@section('foot_path', ' · Itinerary ' . $trip->trip_reference)

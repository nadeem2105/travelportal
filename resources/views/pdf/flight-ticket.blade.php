@extends('pdf.layout')

@section('pdf_title', 'Flight E-Ticket ' . $booking->booking_reference)

@section('foot_path')/booking/{{ $booking->booking_reference }}/eticket

@section('pdf_content')
    @section('doc_badge')
        <span class="doc-label blue">FLIGHT E-TICKET</span>
    @endsection
    @section('doc_right')
        <div class="doc-big">Booking #{{ $booking->booking_reference }}</div>
        <div class="doc-line">Issue Date: <strong style="color:#0f172a">{{ now()->format('d M Y') }}</strong></div>
        <div class="doc-line">Status: <span class="status-green">CONFIRMED</span></div>
    @endsection

    @include('pdf.partials.brand-header')

    {{-- PNR card --}}
    <div class="card">
        <table class="w">
            <tr>
                <td class="vtop">
                    <p class="lbl">Airline PNR</p>
                    <p class="val big blue">{{ $booking->flight?->pnr ?? '—' }}</p>
                </td>
                <td class="vtop">
                    <p class="lbl">E-Ticket Number</p>
                    <p class="val">{{ $booking->flight?->ticket_number ?? '—' }}</p>
                </td>
                <td class="vtop doc-right">
                    <p class="lbl">Cabin</p>
                    <p class="val">{{ ucfirst($booking->flight?->journey['cabin_class'] ?? 'Economy') }}</p>
                </td>
            </tr>
        </table>
    </div>

    <h2 class="sec with-icon">Flight Itinerary</h2>
    @foreach ($booking->flight?->journey['segments'] ?? [] as $seg)
        <div class="card">
            <table class="w">
                <tr>
                    <td class="vtop" width="33%">
                        <p class="lbl">Depart</p>
                        <p class="val big">{{ $seg['from']['time'] ?? '' }}</p>
                        <p class="muted">{{ $seg['from']['city'] ?? '' }} ({{ $seg['from']['code'] ?? '' }})</p>
                        <p class="muted">{{ isset($seg['from']['date']) ? \Carbon\Carbon::parse($seg['from']['date'])->format('D, d M Y') : '' }}</p>
                    </td>
                    <td class="vtop" style="text-align:center" width="33%">
                        <p class="lbl">Duration</p>
                        @php($mins = $seg['duration_minutes'] ?? 0)
                        <p class="val">{{ floor($mins / 60) }}h {{ $mins % 60 }}m</p>
                        <p class="muted">{{ ($seg['stops'] ?? 0) === 0 ? 'Non-stop' : 'Via ' . ($seg['stopover']['airport'] ?? 'connection') }}</p>
                    </td>
                    <td class="vtop doc-right" width="33%">
                        <p class="lbl">Arrive</p>
                        <p class="val big">{{ $seg['to']['time'] ?? '' }}</p>
                        <p class="muted">{{ $seg['to']['city'] ?? '' }} ({{ $seg['to']['code'] ?? '' }})</p>
                    </td>
                </tr>
            </table>
            <p class="muted" style="margin-top:8px">
                Airline: <strong style="color:#0f172a">{{ $booking->flight?->airline_code }}-{{ $seg['flight_number'] ?? $booking->flight?->flight_number }}</strong>
                · Baggage: <strong style="color:#0f172a">{{ $seg['baggage']['check_in'] ?? 'As per airline' }}</strong> + {{ $seg['baggage']['cabin'] ?? '7 Kg' }} cabin
            </p>
        </div>
    @endforeach

    <h2 class="sec with-icon">Travellers</h2>
    <table class="data">
        <thead><tr><th style="width:36px">#</th><th>Passenger Name</th></tr></thead>
        <tbody>
            @foreach ($booking->travellers as $i => $traveller)
                <tr><td>{{ $i + 1 }}</td><td><strong>{{ $traveller->full_name }}</strong> <span class="pill" style="margin-left:4px">{{ ucfirst($traveller->traveller_type) }}</span></td></tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="row grand">Total Paid ({{ strtoupper($booking->currency) }}) <span style="float:right">{{ money($booking->total_amount) }}</span></div>
    </div>

    <div class="note" style="border:1px solid #e2e8f0; background:#f8fafc; padding:10px 12px; font-size:10.5px; color:#475569; border-radius:6px; margin-top:16px">
        <strong>Important:</strong> Carry a valid government photo ID at check-in. Web check-in opens 48 hours before departure on the airline's website.
        {{ $booking->flight?->fare_rules['cancellation'] ?? '' }}
    </div>
@endsection

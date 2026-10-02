@extends('emails.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 24px;">
        <span class="badge badge-success">✓ E-Ticket Issued</span>
        <h2 style="font-size: 22px; color: #0f172a; margin: 12px 0 4px 0;">Your Flight E-Ticket is Confirmed!</h2>
        <p style="color: #64748b; font-size: 14px; margin: 0;">Booking Reference: <strong style="font-family: monospace; color: #0f766e; font-size: 16px;">{{ $booking->booking_reference }}</strong></p>
    </div>

    <p style="font-size: 15px;">Dear <strong>{{ $customerName }}</strong>,</p>
    <p style="font-size: 14px;">Your electronic airline ticket has been issued. Below are your flight itinerary details and airline reference.</p>

    @php
        $segment = $booking->flight?->journey['segments'][0] ?? null;
    @endphp

    <div class="details-box">
        <h3 style="margin-top: 0; margin-bottom: 14px; font-size: 16px; color: #0f766e; border-bottom: 2px solid #ccfbf1; padding-bottom: 6px;">
            Flight Segment Overview
        </h3>

        <table width="100%" cellpadding="6" cellspacing="0">
            <tr>
                <td class="details-label">Route:</td>
                <td class="details-val">{{ $segment['from']['city'] ?? 'Origin' }} ({{ $segment['from']['code'] ?? '' }}) → {{ $segment['to']['city'] ?? 'Destination' }} ({{ $segment['to']['code'] ?? '' }})</td>
            </tr>
            @if (!empty($booking->supplier_booking_id) || !empty($booking->flight?->pnr))
            <tr>
                <td class="details-label">Airline PNR:</td>
                <td class="details-val" style="font-family: monospace; color: #0f766e; font-size: 16px;">{{ $booking->flight?->pnr ?? $booking->supplier_booking_id }}</td>
            </tr>
            @endif
            @if ($segment)
            <tr>
                <td class="details-label">Flight:</td>
                <td class="details-val">{{ $segment['airline']['name'] ?? 'Airline' }} · {{ $segment['flight_number'] ?? '' }}</td>
            </tr>
            <tr>
                <td class="details-label">Departure:</td>
                <td class="details-val">{{ $segment['from']['date'] ?? '' }} at {{ $segment['from']['time'] ?? '' }}</td>
            </tr>
            <tr>
                <td class="details-label">Arrival:</td>
                <td class="details-val">{{ $segment['to']['date'] ?? '' }} at {{ $segment['to']['time'] ?? '' }}</td>
            </tr>
            @endif
            <tr>
                <td class="details-label">Total Fare Paid:</td>
                <td class="details-val" style="color: #15803d;">₹{{ number_format((float)$booking->total_amount, 2) }}</td>
            </tr>
        </table>
    </div>

    @if ($booking->travellers->count() > 0)
    <div style="margin-bottom: 20px;">
        <strong style="font-size: 13px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Passengers:</strong>
        <ul style="margin: 8px 0; padding-left: 20px; font-size: 14px; color: #334155;">
            @foreach ($booking->travellers as $t)
                <li>{{ $t->first_name }} {{ $t->last_name }} ({{ ucfirst($t->traveller_type ?? 'Adult') }})</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div style="background-color: #f1f5f9; border-radius: 6px; padding: 12px 16px; font-size: 13px; color: #334155; margin-bottom: 20px;">
        ✈️ <strong>Airport Advisory:</strong> Please arrive at the airport at least 2 hours prior to domestic flight departure with valid government identification.
    </div>

    <div style="text-align: center; margin: 30px 0 10px 0;">
        <a href="{{ route('account.booking.itinerary', $booking) }}" class="btn" target="_blank" style="margin-right: 8px;">
            ✈️ View Electronic Ticket
        </a>
        <a href="{{ route('account.booking.invoice', $booking) }}" class="btn btn-secondary" target="_blank">
            🧾 View Tax Invoice
        </a>
    </div>
@endsection

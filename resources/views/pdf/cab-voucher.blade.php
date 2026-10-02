@extends('pdf.layout')

@section('pdf_title', 'Cab Voucher ' . $booking->booking_reference)

@section('foot_path')/booking/{{ $booking->booking_reference }}/voucher

@section('pdf_content')
    @section('doc_badge')
        <span class="doc-label blue">CAB BOOKING VOUCHER</span>
    @endsection
    @section('doc_right')
        <div class="doc-big">Booking #{{ $booking->booking_reference }}</div>
        <div class="doc-line">Issue Date: <strong style="color:#0f172a">{{ now()->format('d M Y') }}</strong></div>
        <div class="doc-line">Status: <span class="status-green">CONFIRMED</span></div>
    @endsection

    @include('pdf.partials.brand-header')

    <h2 class="sec with-icon">Trip Details</h2>
    <div class="card">
        <table class="w">
            <tr>
                <td class="vtop" width="50%">
                    <p class="lbl">Vehicle</p>
                    <p class="val big blue">{{ $booking->cab?->vehicle_name ?? '—' }}</p>
                    <p class="muted">{{ ucfirst(str_replace('_', ' ', $booking->cab?->trip_type ?? 'One Way')) }} · approx. {{ $booking->cab?->distance_km }} km</p>
                </td>
                <td class="vtop" width="50%">
                    <p class="lbl">Pickup</p>
                    <p class="val">{{ optional($booking->cab?->pickup_datetime)->format('D, d M Y — h:i A') }}</p>
                    <p class="muted">Be ready 10 minutes before pickup time</p>
                </td>
            </tr>
            <tr><td colspan="2" style="height:14px"></td></tr>
            <tr>
                <td class="vtop">
                    <p class="lbl">Pickup Location</p>
                    <p class="val">{{ $booking->cab?->pickup_location }}</p>
                </td>
                <td class="vtop">
                    <p class="lbl">Drop Location</p>
                    <p class="val">{{ $booking->cab?->drop_location ?? '—' }}</p>
                </td>
            </tr>
            <tr><td colspan="2" style="height:14px"></td></tr>
            <tr>
                <td class="vtop">
                    <p class="lbl">Passenger</p>
                    <p class="val">{{ $booking->travellers->first()?->full_name ?? ($booking->contact['first_name'] ?? 'Guest') }}</p>
                    <p class="muted">{{ $booking->contact['phone'] ?? '' }}</p>
                </td>
                <td class="vtop">
                    <p class="lbl">Driver Details</p>
                    <p class="val">{{ $booking->cab?->driver_details['name'] ?? 'Will be shared before pickup' }}</p>
                </td>
            </tr>
        </table>
    </div>

    <h2 class="sec with-icon">Fare Summary</h2>
    <div class="totals">
        <div class="row grand">Total Paid ({{ strtoupper($booking->currency) }}) <span style="float:right">{{ money($booking->total_amount) }}</span></div>
    </div>

    <div class="note" style="border:1px solid #e2e8f0; background:#f8fafc; padding:10px 12px; font-size:10.5px; color:#475569; border-radius:6px; margin-top:16px">
        <strong>Inclusions:</strong> Fuel, driver allowance, tolls and parking as per package.
        <strong>Cancellation:</strong> Free up to 12 hours before pickup; 50% charge within 12 hours; no-show is non-refundable.
    </div>
@endsection

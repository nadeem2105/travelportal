@extends('pdf.layout')

@section('pdf_title', 'Hotel Voucher ' . $booking->booking_reference)

@section('foot_path')/booking/{{ $booking->booking_reference }}/voucher

@section('pdf_content')
    @section('doc_badge')
        <span class="doc-label blue">HOTEL VOUCHER</span>
    @endsection
    @section('doc_right')
        <div class="doc-big">Booking #{{ $booking->booking_reference }}</div>
        <div class="doc-line">Issue Date: <strong style="color:#0f172a">{{ now()->format('d M Y') }}</strong></div>
        <div class="doc-line">Status: <span class="status-green">CONFIRMED</span></div>
    @endsection

    @include('pdf.partials.brand-header')

    <h2 class="sec with-icon">Stay Details</h2>
    <div class="card">
        <table class="w">
            <tr>
                <td class="vtop" width="50%">
                    <p class="lbl">Hotel</p>
                    <p class="val big blue">{{ $booking->hotelBooking?->hotel_name ?? '—' }}</p>
                    <p class="muted">{{ $booking->hotelBooking?->hotel?->address ?? ($booking->hotelBooking?->hotel?->city ?? '') }}</p>
                </td>
                <td class="vtop" width="50%">
                    <p class="lbl">Room &amp; Meal Plan</p>
                    <p class="val">{{ $booking->hotelBooking?->room_type ?? '—' }}</p>
                    <p class="muted">{{ ucfirst(str_replace('_', ' ', $booking->hotelBooking?->meal_plan ?? 'Room Only')) }}</p>
                </td>
            </tr>
            <tr><td colspan="2" style="height:14px"></td></tr>
            <tr>
                <td class="vtop">
                    <p class="lbl">Check In</p>
                    <p class="val">{{ optional($booking->hotelBooking?->check_in)->format('D, d M Y') }}</p>
                    <p class="muted">From 2:00 PM</p>
                </td>
                <td class="vtop">
                    <p class="lbl">Check Out</p>
                    <p class="val">{{ optional($booking->hotelBooking?->check_out)->format('D, d M Y') }}</p>
                    <p class="muted">By 12:00 PM · {{ $booking->hotelBooking?->nights ?? '' }} Night(s)</p>
                </td>
            </tr>
            <tr><td colspan="2" style="height:14px"></td></tr>
            <tr>
                <td class="vtop">
                    <p class="lbl">Primary Guest</p>
                    <p class="val">{{ $booking->travellers->first()?->full_name ?? ($booking->contact['first_name'] ?? 'Guest') }}</p>
                    <p class="muted">{{ $booking->travellers->count() }} Guest(s) · {{ $booking->hotelBooking?->rooms ?? 1 }} Room(s)</p>
                </td>
                <td class="vtop">
                    <p class="lbl">Supplier Reference</p>
                    <p class="val">{{ $booking->hotelBooking?->supplier_booking_id ?? 'Direct booking' }}</p>
                </td>
            </tr>
        </table>
    </div>

    <h2 class="sec with-icon">Charges</h2>
    <table class="data">
        <thead><tr><th>Description</th><th class="right" style="width:120px">Amount</th></tr></thead>
        <tbody>
            @foreach ($booking->items as $item)
                <tr><td>{{ $item->name }}</td><td class="right">{{ money($item->total_price) }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <div class="totals">
        <div class="row grand">Total Paid ({{ strtoupper($booking->currency) }}) <span style="float:right">{{ money($booking->total_amount) }}</span></div>
    </div>

    @php($policies = $booking->hotelBooking?->hotel?->policies)
    <div class="note" style="border:1px solid #e2e8f0; background:#f8fafc; padding:10px 12px; font-size:10.5px; color:#475569; border-radius:6px; margin-top:16px">
        <strong>Check-in instructions:</strong> Present this voucher along with a valid government photo ID at the reception.
        @if ($policies)
            <br><strong>Hotel policies:</strong> {{ implode(' · ', array_slice($policies, 0, 4)) }}
        @endif
    </div>
@endsection

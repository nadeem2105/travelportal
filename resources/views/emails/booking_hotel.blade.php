@extends('emails.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 24px;">
        <span class="badge badge-success">✓ Reservation Confirmed</span>
        <h2 style="font-size: 22px; color: #0f172a; margin: 12px 0 4px 0;">Your Hotel Stay is Confirmed!</h2>
        <p style="color: #64748b; font-size: 14px; margin: 0;">Booking Reference: <strong style="font-family: monospace; color: #0f766e; font-size: 16px;">{{ $booking->booking_reference }}</strong></p>
    </div>

    <p style="font-size: 15px;">Dear <strong>{{ $customerName }}</strong>,</p>
    <p style="font-size: 14px;">Your hotel reservation has been confirmed with the property. Below are the complete check-in instructions and stay details.</p>

    <div class="details-box">
        <h3 style="margin-top: 0; margin-bottom: 14px; font-size: 16px; color: #0f766e; border-bottom: 2px solid #ccfbf1; padding-bottom: 6px;">
            Stay Summary
        </h3>

        <table width="100%" cellpadding="6" cellspacing="0">
            <tr>
                <td class="details-label">Hotel:</td>
                <td class="details-val">{{ $booking->hotelBooking?->hotel_name ?? 'Premium Hotel' }}</td>
            </tr>
            @if ($booking->hotelBooking?->hotel?->address)
            <tr>
                <td class="details-label">Location:</td>
                <td class="details-val" style="font-weight: 500;">{{ $booking->hotelBooking->hotel->address }}, {{ $booking->hotelBooking->hotel->city }}</td>
            </tr>
            @endif
            <tr>
                <td class="details-label">Check-in:</td>
                <td class="details-val">{{ $booking->hotelBooking?->check_in?->format('d M Y') ?? 'Confirmed' }} (from 12:00 PM)</td>
            </tr>
            <tr>
                <td class="details-label">Check-out:</td>
                <td class="details-val">{{ $booking->hotelBooking?->check_out?->format('d M Y') ?? 'Confirmed' }} (until 11:00 AM)</td>
            </tr>
            <tr>
                <td class="details-label">Duration:</td>
                <td class="details-val">{{ $booking->hotelBooking?->nights ?? 1 }} Night(s) · {{ $booking->hotelBooking?->rooms_count ?? 1 }} Room(s)</td>
            </tr>
            @if ($booking->hotelBooking?->room_type)
            <tr>
                <td class="details-label">Room Type & Meal:</td>
                <td class="details-val">{{ $booking->hotelBooking->room_type }} ({{ strtoupper($booking->hotelBooking->meal_plan ?? 'EP') }})</td>
            </tr>
            @endif
            <tr>
                <td class="details-label">Total Amount Paid:</td>
                <td class="details-val" style="color: #15803d;">₹{{ number_format((float)$booking->total_amount, 2) }}</td>
            </tr>
        </table>
    </div>

    <div style="background-color: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; padding: 12px 16px; font-size: 13px; color: #92400e; margin-bottom: 20px;">
        <strong>Check-in Reminder:</strong> Please present a government-approved photo ID (Aadhaar, Passport, Voter ID) for all guests upon arrival at the reception.
    </div>

    <div style="text-align: center; margin: 30px 0 10px 0;">
        <a href="{{ route('account.booking.itinerary', $booking) }}" class="btn" target="_blank" style="margin-right: 8px;">
            🏨 Download Hotel Voucher
        </a>
        <a href="{{ route('account.booking.invoice', $booking) }}" class="btn btn-secondary" target="_blank">
            🧾 View Tax Invoice
        </a>
    </div>
@endsection

@extends('emails.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 24px;">
        <span class="badge badge-success">✓ Booking Confirmed</span>
        <h2 style="font-size: 22px; color: #0f172a; margin: 12px 0 4px 0;">Your Tour is Confirmed!</h2>
        <p style="color: #64748b; font-size: 14px; margin: 0;">Booking Reference: <strong style="font-family: monospace; color: #0f766e; font-size: 16px;">{{ $booking->booking_reference }}</strong></p>
    </div>

    <p style="font-size: 15px;">Dear <strong>{{ $customerName }}</strong>,</p>
    <p style="font-size: 14px;">Great news! Your holiday reservation with Leemroz Travels has been confirmed. We have reserved your itinerary and your Kashmir journey is all set.</p>

    <div class="details-box">
        <h3 style="margin-top: 0; margin-bottom: 14px; font-size: 16px; color: #0f766e; border-bottom: 2px solid #ccfbf1; padding-bottom: 6px;">
            Tour Reservation Details
        </h3>

        <table width="100%" cellpadding="6" cellspacing="0">
            <tr>
                <td class="details-label">Package:</td>
                <td class="details-val">{{ $booking->packageBooking?->package_name ?? 'Curated Kashmir Package' }}</td>
            </tr>
            @if ($booking->packageBooking?->departure_date)
            <tr>
                <td class="details-label">Departure Date:</td>
                <td class="details-val">{{ $booking->packageBooking->departure_date->format('d M Y') }}</td>
            </tr>
            @endif
            @if ($booking->packageBooking?->package?->duration_days)
            <tr>
                <td class="details-label">Duration:</td>
                <td class="details-val">{{ $booking->packageBooking->package->duration_days }} Days / {{ $booking->packageBooking->package->duration_nights }} Nights</td>
            </tr>
            @endif
            <tr>
                <td class="details-label">Total Travellers:</td>
                <td class="details-val">{{ $booking->travellers->count() > 0 ? $booking->travellers->count() . ' Person(s)' : '1 Person' }}</td>
            </tr>
            <tr>
                <td class="details-label">Payment Status:</td>
                <td class="details-val" style="color: #15803d;">Paid in Full (₹{{ number_format((float)$booking->total_amount, 2) }})</td>
            </tr>
        </table>
    </div>

    @if ($booking->bookingHotels->count() > 0)
    <div class="details-box">
        <h3 style="margin-top: 0; margin-bottom: 14px; font-size: 16px; color: #0f766e; border-bottom: 2px solid #ccfbf1; padding-bottom: 6px;">
            Hotel Details
        </h3>
        @foreach ($booking->bookingHotels as $hotel)
            <table width="100%" cellpadding="6" cellspacing="0" style="{{ !$loop->first ? 'border-top:1px solid #e2e8f0; margin-top:8px;' : '' }}">
                <tr>
                    <td class="details-label">Hotel:</td>
                    <td class="details-val">
                        {{ $hotel->hotel_name_snapshot }}
                        @if ($hotel->star_rating_snapshot) ({{ $hotel->star_rating_snapshot }}★) @endif
                        @if ($hotel->segment_label) <span style="color:#64748b;">· {{ $hotel->segment_label }}</span> @endif
                    </td>
                </tr>
                @if ($hotel->room_name_snapshot)
                <tr><td class="details-label">Room:</td><td class="details-val">{{ $hotel->room_name_snapshot }}</td></tr>
                @endif
                <tr><td class="details-label">Meal Plan:</td><td class="details-val">{{ $hotel->mealPlanLabel() }}</td></tr>
                @if ($hotel->check_in)
                <tr><td class="details-label">Check-in:</td><td class="details-val">{{ $hotel->check_in->format('d M Y') }}</td></tr>
                @endif
                @if ($hotel->check_out)
                <tr><td class="details-label">Check-out:</td><td class="details-val">{{ $hotel->check_out->format('d M Y') }} ({{ $hotel->nights }} Night(s))</td></tr>
                @endif
                <tr><td class="details-label">Guests:</td><td class="details-val">{{ $hotel->guestSummary() }} · {{ $hotel->rooms }} Room(s)</td></tr>
                @if ($hotel->cancellation_policy_snapshot)
                <tr><td class="details-label">Cancellation:</td><td class="details-val">{{ $hotel->cancellation_policy_snapshot }}</td></tr>
                @endif
            </table>
        @endforeach
    </div>
    @endif

    @if ($booking->packageFlights->count() > 0)
    <div class="details-box">
        <h3 style="margin-top: 0; margin-bottom: 14px; font-size: 16px; color: #0f766e; border-bottom: 2px solid #ccfbf1; padding-bottom: 6px;">
            Flight Details
        </h3>
        @foreach ($booking->packageFlights as $pf)
            <table width="100%" cellpadding="6" cellspacing="0" style="{{ !$loop->first ? 'border-top:1px solid #e2e8f0; margin-top:8px;' : '' }}">
                <tr><td class="details-label">Flight:</td><td class="details-val">{{ $pf->label_snapshot }}@if ($pf->airline_snapshot) · {{ $pf->airline_snapshot }}@endif</td></tr>
                <tr><td class="details-label">Route:</td><td class="details-val">{{ $pf->routeLabel() }}</td></tr>
                <tr><td class="details-label">Cabin / Trip:</td><td class="details-val">{{ $pf->cabinLabel() }} · {{ ucfirst(str_replace('_',' ',$pf->trip_type)) }}</td></tr>
                <tr><td class="details-label">Travellers:</td><td class="details-val">{{ $pf->travellers }}</td></tr>
                @if ($pf->cancellation_policy_snapshot)
                <tr><td class="details-label">Cancellation:</td><td class="details-val">{{ $pf->cancellation_policy_snapshot }}</td></tr>
                @endif
            </table>
        @endforeach
    </div>
    @endif

    @if ($booking->travellers->count() > 0)
    <div style="margin-bottom: 20px;">
        <strong style="font-size: 13px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Registered Travellers:</strong>
        <ul style="margin: 8px 0; padding-left: 20px; font-size: 14px; color: #334155;">
            @foreach ($booking->travellers as $t)
                <li>{{ $t->first_name }} {{ $t->last_name }} ({{ ucfirst($t->traveller_type ?? 'Adult') }})</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div style="text-align: center; margin: 30px 0 10px 0;">
        <a href="{{ route('account.booking.itinerary', $booking) }}" class="btn" target="_blank" style="margin-right: 8px;">
            📄 Download Tour Itinerary
        </a>
        <a href="{{ route('account.booking.invoice', $booking) }}" class="btn btn-secondary" target="_blank">
            🧾 View Tax Invoice
        </a>
    </div>
@endsection

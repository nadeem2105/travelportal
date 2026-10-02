@extends('emails.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 24px;">
        <span class="badge badge-success">✓ Cab Reserved</span>
        <h2 style="font-size: 22px; color: #0f172a; margin: 12px 0 4px 0;">Your Cab Booking is Confirmed!</h2>
        <p style="color: #64748b; font-size: 14px; margin: 0;">Booking Reference: <strong style="font-family: monospace; color: #0f766e; font-size: 16px;">{{ $booking->booking_reference }}</strong></p>
    </div>

    <p style="font-size: 15px;">Dear <strong>{{ $customerName }}</strong>,</p>
    <p style="font-size: 14px;">Your private transfer has been reserved. Below are your journey schedule and vehicle details.</p>

    <div class="details-box">
        <h3 style="margin-top: 0; margin-bottom: 14px; font-size: 16px; color: #0f766e; border-bottom: 2px solid #ccfbf1; padding-bottom: 6px;">
            Journey & Route Details
        </h3>

        <table width="100%" cellpadding="6" cellspacing="0">
            <tr>
                <td class="details-label">Route:</td>
                <td class="details-val">{{ $booking->cab?->pickup_location ?? 'Pickup Point' }} → {{ $booking->cab?->drop_location ?? 'Drop Point' }}</td>
            </tr>
            @if ($booking->cab?->pickup_datetime)
            <tr>
                <td class="details-label">Pickup Schedule:</td>
                <td class="details-val" style="color: #0f766e;">{{ $booking->cab->pickup_datetime->format('d M Y, h:i A') }}</td>
            </tr>
            @endif
            <tr>
                <td class="details-label">Vehicle Category:</td>
                <td class="details-val">{{ $booking->cab?->vehicle?->name ?? 'Standard Sedan / SUV' }}</td>
            </tr>
            <tr>
                <td class="details-label">Trip Type:</td>
                <td class="details-val">{{ ucfirst($booking->cab?->trip_type ?? 'one_way') }}</td>
            </tr>
            <tr>
                <td class="details-label">Total Fare Paid:</td>
                <td class="details-val" style="color: #15803d;">₹{{ number_format((float)$booking->total_amount, 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- Driver Assignment Card --}}
    @if (!empty($booking->cab?->driver_name) || !empty($booking->cab?->driver_phone))
        <div style="background-color: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 18px; margin: 20px 0;">
            <h4 style="margin: 0 0 10px 0; color: #065f46; font-size: 15px;">🚖 Assigned Chauffeur & Vehicle</h4>
            <table width="100%" cellpadding="4" cellspacing="0" style="font-size: 14px;">
                <tr>
                    <td style="color: #047857; font-weight: 500;">Driver Name:</td>
                    <td style="font-weight: 700; color: #064e3b; text-align: right;">{{ $booking->cab->driver_name }}</td>
                </tr>
                <tr>
                    <td style="color: #047857; font-weight: 500;">Driver Phone:</td>
                    <td style="font-weight: 700; color: #064e3b; text-align: right;"><a href="tel:{{ $booking->cab->driver_phone }}" style="color: #0f766e;">{{ $booking->cab->driver_phone }}</a></td>
                </tr>
                @if (!empty($booking->cab->vehicle_number))
                <tr>
                    <td style="color: #047857; font-weight: 500;">Vehicle Registration:</td>
                    <td style="font-weight: 700; color: #064e3b; text-align: right; font-family: monospace;">{{ $booking->cab->vehicle_number }}</td>
                </tr>
                @endif
            </table>
        </div>
    @else
        <div style="background-color: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 14px 18px; font-size: 13px; color: #475569; margin-bottom: 20px;">
            ℹ️ <strong>Driver Assignment:</strong> Your chauffeur's contact number and vehicle registration plate will be sent via SMS & WhatsApp approximately 2 hours before scheduled pickup.
        </div>
    @endif

    <div style="text-align: center; margin: 30px 0 10px 0;">
        <a href="{{ route('account.booking.itinerary', $booking) }}" class="btn" target="_blank" style="margin-right: 8px;">
            🚕 Download Cab Voucher
        </a>
        <a href="{{ route('account.booking.invoice', $booking) }}" class="btn btn-secondary" target="_blank">
            🧾 View Tax Invoice
        </a>
    </div>
@endsection

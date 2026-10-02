@extends('emails.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 24px;">
        <span class="badge badge-amber">Cancellation Processed</span>
        <h2 style="font-size: 22px; color: #0f172a; margin: 12px 0 4px 0;">Booking Cancellation & Refund Notice</h2>
        <p style="color: #64748b; font-size: 14px; margin: 0;">Booking Reference: <strong style="font-family: monospace; color: #0f766e; font-size: 16px;">{{ $booking->booking_reference }}</strong></p>
    </div>

    <p style="font-size: 15px;">Dear <strong>{{ $customerName }}</strong>,</p>
    <p style="font-size: 14px;">We have successfully processed the cancellation request for your reservation. Your itinerary has been cancelled as per your request.</p>

    <div class="details-box">
        <h3 style="margin-top: 0; margin-bottom: 14px; font-size: 16px; color: #0f766e; border-bottom: 2px solid #ccfbf1; padding-bottom: 6px;">
            Refund Statement
        </h3>

        <table width="100%" cellpadding="6" cellspacing="0">
            <tr>
                <td class="details-label">Product Type:</td>
                <td class="details-val" style="text-transform: capitalize;">{{ $booking->product_type }}</td>
            </tr>
            <tr>
                <td class="details-label">Original Booking Value:</td>
                <td class="details-val">₹{{ number_format((float)$booking->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td class="details-label">Approved Refund Amount:</td>
                <td class="details-val" style="color: #15803d; font-size: 16px;">₹{{ number_format((float)$refundAmount, 2) }}</td>
            </tr>
            <tr>
                <td class="details-label">Estimated Credit Window:</td>
                <td class="details-val" style="color: #b45309;">{{ $refundDays }} Business Days</td>
            </tr>
            <tr>
                <td class="details-label">Settlement Mode:</td>
                <td class="details-val">Original Payment Method (Reversal)</td>
            </tr>
        </table>
    </div>

    <p style="font-size: 13px; color: #64748b;">
        Depending on your bank or credit card issuer, it may take between {{ $refundDays }} working days for the credit reversal to reflect on your account statement.
    </p>

    <div style="text-align: center; margin: 30px 0 10px 0;">
        <a href="{{ route('account.booking.show', $booking) }}" class="btn">
            View Cancellation Status
        </a>
    </div>
@endsection

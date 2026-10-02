{{-- Printable Tax Invoice --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tax Invoice {{ $booking->booking_reference }} — {{ settings('company_name', 'Leemroz Travels') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #2563eb;
            --brand-dark: #1d4ed8;
            --ink: #0f172a;
            --muted: #64748b;
            --light-bg: #f8fafc;
            --border: #e2e8f0;
            --emerald: #059669;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--ink);
            background: #f1f5f9;
            padding: 30px 16px;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
            padding: 40px;
        }
        .no-print-bar {
            max-width: 900px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 9999px;
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s;
        }
        .btn-primary {
            background: var(--brand);
            color: #fff;
        }
        .btn-primary:hover { background: var(--brand-dark); }
        .btn-outline {
            background: #fff;
            color: var(--ink);
            border-color: var(--border);
        }
        .btn-outline:hover { background: var(--light-bg); color: var(--brand); }

        /* Header */
        .head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--border);
            padding-bottom: 24px;
            gap: 20px;
        }
        .brand {
            font-size: 24px;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.5px;
            line-height: 1.2;
        }
        .brand span { color: var(--brand); }
        .brand small {
            display: block;
            color: var(--brand);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
        .badge {
            display: inline-block;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 9999px;
            padding: 5px 14px;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .badge.confirmed { background: #ecfdf5; color: var(--emerald); }
        .badge.cancelled { background: #fef2f2; color: #ef4444; }

        /* Billing info */
        .billing-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }
        @media (max-width: 600px) {
            .billing-grid { grid-template-columns: 1fr; }
        }
        .info-col h4 {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--muted);
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .info-col p {
            font-size: 13.5px;
            line-height: 1.5;
            color: #334155;
        }
        .info-col p strong {
            color: var(--ink);
            font-size: 15px;
        }

        /* Travel Summary Card */
        .summary-card {
            margin-top: 20px;
            background: var(--light-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .summary-card h3 {
            font-size: 15px;
            font-weight: 700;
            color: var(--ink);
        }
        .summary-card p {
            font-size: 12.5px;
            color: var(--muted);
            margin-top: 2px;
        }
        .summary-badge {
            background: #fff;
            border: 1px solid var(--border);
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #1e40af;
        }

        /* Table */
        table.invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
            font-size: 13.5px;
        }
        table.invoice-table th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
            background: var(--light-bg);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 12px 14px;
        }
        table.invoice-table td {
            padding: 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        /* Totals */
        .totals-section {
            margin-top: 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 24px;
        }
        .payment-meta-box {
            flex: 1;
            min-width: 240px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 18px;
            font-size: 12.5px;
        }
        .payment-meta-box h4 {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 8px;
        }
        .payment-meta-box p {
            color: #475569;
            margin-bottom: 4px;
            line-height: 1.4;
        }
        .totals {
            width: 320px;
            font-size: 13.5px;
        }
        .totals div {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            color: #334155;
        }
        .totals .grand {
            font-weight: 800;
            font-size: 16px;
            border-top: 2px solid var(--ink);
            margin-top: 8px;
            padding-top: 12px !important;
            color: var(--ink);
        }

        /* Footer */
        .footer {
            margin-top: 40px;
            border-top: 1px solid var(--border);
            padding-top: 20px;
            font-size: 11.5px;
            color: var(--muted);
            line-height: 1.6;
        }

        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .container { box-shadow: none; padding: 20px; max-width: 100%; border-radius: 0; }
            .no-print-bar { display: none !important; }
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    </style>
</head>
<body>

    {{-- Top Action Bar --}}
    <div class="no-print-bar">
        <div style="display: flex; gap: 8px;">
            <button class="btn btn-primary" onclick="window.print()">
                <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg>
                Print / Save as PDF
            </button>
            @php
                $itineraryUrl = auth('admin')->check() 
                    ? route('admin.bookings.itinerary', $booking) 
                    : route('booking.itinerary', $booking);
            @endphp
            <a href="{{ $itineraryUrl }}" class="btn btn-outline">
                <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/></svg>
                View Itinerary &amp; Voucher
            </a>
        </div>
        <button class="btn btn-outline" onclick="window.history.length > 1 ? window.history.back() : window.close()">
            ✕ Close
        </button>
    </div>

    <div class="container">
        {{-- Header --}}
        <div class="head">
            <div>
                <div class="brand">
                    {{ settings('company_name', 'Leemroz Travels') }}
                    <small>{{ settings('company_tagline', 'Explore · Book · Experience') }}</small>
                </div>
                <p style="font-size: 12px; color: var(--muted); margin-top: 6px;">
                    {{ settings('company_address', 'Ishber Nishat Gupt Ganga, Srinagar J&K -190025') }}<br>
                    Tel: {{ settings('company_phone', '+91 70069 76447') }}
                </p>
            </div>
            <div style="text-align:right">
                <span class="badge {{ in_array($booking->status, ['confirmed', 'completed']) ? 'confirmed' : ($booking->status === 'cancelled' ? 'cancelled' : '') }}">
                    {{ strtoupper(label_case($booking->status)) }}
                </span>
                <h2 style="font-size: 18px; font-weight: 800; margin-top: 4px;">TAX INVOICE</h2>
                <p style="font-size: 12px; color: var(--muted); margin-top: 4px;">Invoice Date: <strong>{{ now()->format('d M Y') }}</strong></p>
                <p style="font-size: 12px; color: var(--muted);">Booking Reference: <strong style="color: var(--brand);">{{ $booking->booking_reference }}</strong></p>
            </div>
        </div>

        {{-- Billing Info --}}
        <div class="billing-grid">
            <div class="info-col">
                <h4>Billed To (Customer Details)</h4>
                <p><strong>{{ $booking->user?->name ?? $booking->contact['first_name'] ?? 'Guest Traveller' }}</strong></p>
                <p>Email: {{ $booking->contact['email'] ?? $booking->user?->email }}</p>
                <p>Phone: {{ $booking->contact['phone'] ?? $booking->user?->phone }}</p>
                @if ($booking->user?->address)
                    <p>{{ $booking->user->address }}, {{ $booking->user->city }}</p>
                @endif
            </div>
            <div class="info-col" style="text-align: right;">
                <h4>Service Provider (Issued By)</h4>
                <p><strong>{{ settings('company_name', 'Leemroz Travels') }}</strong></p>
                <p>{{ settings('company_address') }}</p>
                <p>{{ settings('company_email') }}</p>
                @if (settings('company_registration'))<p>Reg. No: {{ settings('company_registration') }}</p>@endif
            </div>
        </div>

        {{-- Travel Summary Card --}}
        @php
            $pkg = $booking->packageBooking?->package ?? \App\Models\Package::find($booking->product_id ?? $booking->packageBooking?->package_id);
            $flight = $booking->flight;
            $hotel = $booking->hotelBooking?->hotel ?? \App\Models\Hotel::find($booking->product_id ?? $booking->hotelBooking?->hotel_id);
            $hotelBooking = $booking->hotelBooking;
            $cab = $booking->cab;
            $vehicle = $cab?->vehicle ?? \App\Models\Vehicle::find($booking->product_id ?? $cab?->vehicle_id);
        @endphp

        <div class="summary-card">
            <div>
                @if ($booking->product_type === 'package')
                    <h3>Package: {{ $pkg?->name ?? $booking->items->first()?->name }}</h3>
                    <p>Departure: <strong>{{ optional($booking->packageBooking?->departure_date)->format('d M Y') }}</strong> · Duration: {{ $pkg?->duration_days ?? 5 }}D / {{ $pkg?->duration_nights ?? 4 }}N · Destination: {{ $pkg?->destination?->name ?? 'Kashmir' }}</p>
                @elseif ($booking->product_type === 'flight')
                    <h3>Flight: {{ $flight?->airline_code }} {{ $flight?->flight_number }} ({{ $flight?->journey['segments'][0]['from']['code'] ?? 'DEL' }} → {{ $flight?->journey['segments'][0]['to']['code'] ?? 'SXR' }})</h3>
                    <p>PNR: <strong>{{ $flight?->pnr ?? 'Confirmed' }}</strong> · Travel Date: <strong>{{ \Carbon\Carbon::parse($flight?->journey['segments'][0]['from']['date'] ?? now())->format('d M Y') }}</strong> · Class: {{ ucfirst($flight?->journey['cabin_class'] ?? 'Economy') }}</p>
                @elseif ($booking->product_type === 'hotel')
                    <h3>Hotel: {{ $hotel?->name ?? $hotelBooking?->hotel_name }}</h3>
                    <p>Check-In: <strong>{{ optional($hotelBooking?->check_in)->format('d M Y') }}</strong> · Check-Out: <strong>{{ optional($hotelBooking?->check_out)->format('d M Y') }}</strong> · {{ $hotelBooking?->nights ?? 1 }} Night(s), {{ $hotelBooking?->rooms ?? 1 }} Room(s)</p>
                @elseif ($booking->product_type === 'cab')
                    <h3>Cab: {{ $vehicle?->name ?? $cab?->vehicle_name }} ({{ label_case($cab?->trip_type ?? 'one_way') }})</h3>
                    <p>Route: <strong>{{ $cab?->pickup_location }}</strong> ➔ <strong>{{ $cab?->drop_location ?? 'Destination' }}</strong> · Pickup: <strong>{{ optional($cab?->pickup_datetime)->format('d M Y, h:i A') }}</strong></p>
                @endif
            </div>
            <div class="summary-badge">
                {{ ucfirst($booking->product_type) }} Booking
            </div>
        </div>

        {{-- Itemized Table --}}
        <table class="invoice-table">
            <thead>
                <tr>
                    <th style="width: 50%">Item &amp; Description</th>
                    <th style="width: 12%; text-align: center;">Qty</th>
                    <th style="width: 18%; text-align: right;">Unit Rate</th>
                    <th style="width: 20%; text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($booking->items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->name }}</strong>
                            @if ($item->details && is_array($item->details))
                                <div style="font-size: 11.5px; color: var(--muted); margin-top: 2px;">
                                    @foreach ($item->details as $k => $v)
                                        @if(is_string($v)) {{ ucfirst(str_replace('_', ' ', $k)) }}: {{ $v }} · @endif
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td style="text-align: center;">{{ $item->quantity }}</td>
                        <td style="text-align: right;">{{ money($item->unit_price) }}</td>
                        <td style="text-align: right;"><strong>{{ money($item->total_price) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totals & Payment Details --}}
        <div class="totals-section">
            <div class="payment-meta-box">
                <h4>Payment Information</h4>
                @php
                    $payment = $booking->successfulPayment ?? $booking->payments->first();
                @endphp
                @if ($payment)
                    <p>Gateway: <strong>{{ strtoupper($payment->gateway) }}</strong></p>
                    <p>Payment ID: <strong style="font-family: monospace;">{{ $payment->gateway_payment_id ?? $payment->gateway_order_id ?? 'N/A' }}</strong></p>
                    <p>Paid At: {{ $payment->paid_at ? $payment->paid_at->format('d M Y, h:i A') : $payment->created_at->format('d M Y, h:i A') }}</p>
                    <p>Status: <strong style="color: var(--emerald);">Payment Verified &amp; Captured</strong></p>
                @else
                    <p>Status: <strong>{{ label_case($booking->status) }}</strong></p>
                    <p>Currency: <strong>{{ strtoupper($booking->currency) }}</strong></p>
                @endif
                @if ($booking->travellers && $booking->travellers->count() > 0)
                    <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed var(--border);">
                        <span style="font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase;">Passengers / Guests:</span>
                        <div style="font-size: 12px; color: var(--ink); margin-top: 2px;">
                            {{ $booking->travellers->pluck('full_name')->filter()->join(', ') }}
                        </div>
                    </div>
                @endif
            </div>

            <div class="totals">
                <div><span>Subtotal</span><span>{{ money($booking->subtotal) }}</span></div>
                @if ($booking->markup_amount > 0)
                    <div><span>Convenience / Service Fee</span><span>{{ money($booking->markup_amount) }}</span></div>
                @endif
                <div><span>Taxes &amp; GST</span><span>{{ money($booking->tax_amount) }}</span></div>
                @if ($booking->discount_amount > 0)
                    <div style="color: var(--emerald); font-weight: 600;">
                        <span>Coupon Discount</span>
                        <span>-{{ money($booking->discount_amount) }}</span>
                    </div>
                @endif
                <div class="grand">
                    <span>Total Amount Paid</span>
                    <span>{{ money($booking->total_amount) }}</span>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="footer">
            <p><strong>Terms &amp; Conditions:</strong> {{ settings('invoice_terms', 'Thank you for booking with ' . settings('company_name', 'Leemroz Travels') . '. This is a computer-generated tax invoice and requires no physical signature.') }}</p>
            <p style="margin-top: 4px;">Support Helpline: {{ settings('company_phone', '+91 70069 76447') }} | Email: {{ settings('company_email', 'hello@leemroztravels.com') }}</p>
        </div>
    </div>

</body>
</html>

{{-- Printable Itinerary & Travel Voucher --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        @if ($booking->product_type === 'package') Tour Itinerary & Voucher — {{ $booking->booking_reference }}
        @elseif ($booking->product_type === 'flight') Flight E-Ticket — {{ $booking->booking_reference }}
        @elseif ($booking->product_type === 'hotel') Hotel Confirmation Voucher — {{ $booking->booking_reference }}
        @elseif ($booking->product_type === 'cab') Cab Travel Voucher — {{ $booking->booking_reference }}
        @else Travel Voucher — {{ $booking->booking_reference }}
        @endif
    </title>
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
            --amber: #d97706;
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
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--border);
            padding-bottom: 24px;
            gap: 20px;
        }
        .brand-logo {
            font-size: 24px;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.5px;
            line-height: 1.2;
        }
        .brand-logo span { color: var(--brand); }
        .brand-tagline {
            font-size: 11px;
            font-weight: 600;
            color: var(--brand);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
        .company-meta {
            font-size: 12px;
            color: var(--muted);
            margin-top: 8px;
            line-height: 1.5;
        }
        .doc-title-box {
            text-align: right;
        }
        .doc-type-badge {
            display: inline-block;
            background: #eff6ff;
            color: var(--brand-dark);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 4px 12px;
            border-radius: 9999px;
            margin-bottom: 8px;
        }
        .doc-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.3px;
        }
        .doc-meta {
            font-size: 12px;
            color: var(--muted);
            margin-top: 6px;
            line-height: 1.6;
        }
        
        /* Primary Highlight Card */
        .highlight-banner {
            margin-top: 24px;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .highlight-banner h2 {
            font-size: 18px;
            font-weight: 800;
            color: #1e3a8a;
        }
        .highlight-banner p {
            font-size: 13px;
            color: #2563eb;
            font-weight: 500;
            margin-top: 4px;
        }
        .status-tag {
            background: #10b981;
            color: #fff;
            padding: 6px 16px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .status-tag.pending { background: #f59e0b; }
        .status-tag.cancelled { background: #ef4444; }

        /* Overview Grid */
        .overview-grid {
            margin-top: 24px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            background: var(--light-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
        }
        .overview-item h4 {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--muted);
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .overview-item p {
            font-size: 14px;
            font-weight: 700;
            color: var(--ink);
            word-break: break-word;
        }
        .overview-item small {
            display: block;
            font-size: 12px;
            font-weight: 400;
            color: var(--muted);
            margin-top: 2px;
        }

        /* Section Styling */
        .section-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--ink);
            margin-top: 32px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 8px;
            border-bottom: 1.5px solid var(--border);
        }
        .section-title svg {
            width: 20px;
            height: 20px;
            color: var(--brand);
        }

        /* Tables */
        table.voucher-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 13.5px;
        }
        table.voucher-table th {
            text-align: left;
            background: var(--light-bg);
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 14px;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }
        table.voucher-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        /* Day-by-Day Timeline for Packages */
        .itinerary-timeline {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-top: 16px;
        }
        .day-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px 20px;
            background: #fff;
            position: relative;
            page-break-inside: avoid;
        }
        .day-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 8px;
        }
        .day-badge {
            background: #eff6ff;
            color: var(--brand);
            font-size: 12px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 6px;
            text-transform: uppercase;
        }
        .day-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--ink);
            flex: 1;
            margin-left: 10px;
        }
        .day-stay {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            background: #f1f5f9;
            padding: 3px 8px;
            border-radius: 4px;
        }
        .day-description {
            font-size: 13.5px;
            color: #334155;
            line-height: 1.6;
            margin-top: 8px;
        }
        .day-footer {
            margin-top: 12px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        .meal-tag {
            font-size: 11px;
            font-weight: 600;
            color: var(--emerald);
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            padding: 2px 8px;
            border-radius: 4px;
        }

        /* Flight Segment Card */
        .flight-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            background: var(--light-bg);
            margin-top: 12px;
            page-break-inside: avoid;
        }
        .flight-route-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            text-align: center;
        }
        .airport-block {
            flex: 1;
        }
        .airport-code {
            font-size: 28px;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: 0.5px;
        }
        .airport-name {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }
        .airport-time {
            font-size: 15px;
            font-weight: 700;
            color: var(--brand);
            margin-top: 6px;
        }
        .flight-path {
            flex: 1;
            position: relative;
            text-align: center;
        }
        .flight-path-line {
            height: 2px;
            background: #cbd5e1;
            width: 100%;
            margin: 10px 0;
            position: relative;
        }
        .flight-path-plane {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--light-bg);
            padding: 0 8px;
            font-size: 14px;
        }
        .flight-number-badge {
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            background: #fff;
            padding: 4px 10px;
            border-radius: 9999px;
            border: 1px solid var(--border);
            display: inline-block;
        }

        /* Inclusions / Exclusions Split */
        .split-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 12px;
        }
        @media (max-width: 640px) {
            .split-grid { grid-template-columns: 1fr; }
        }
        .split-box {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 18px;
            background: #fff;
            page-break-inside: avoid;
        }
        .split-box h4 {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .split-box ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .split-box li {
            font-size: 12.5px;
            line-height: 1.4;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .check-icon { color: var(--emerald); font-weight: 800; font-size: 14px; }
        .cross-icon { color: #ef4444; font-weight: 800; font-size: 14px; }

        /* Notes & Emergency Contacts */
        .notes-box {
            margin-top: 24px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid var(--brand);
            border-radius: 8px;
            padding: 16px 20px;
            font-size: 12.5px;
            line-height: 1.6;
            color: #334155;
            page-break-inside: avoid;
        }
        .notes-box h4 {
            font-size: 13px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 6px;
        }

        .support-card {
            margin-top: 24px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            border-radius: 12px;
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            page-break-inside: avoid;
        }
        .support-card h4 {
            font-size: 14px;
            font-weight: 700;
            color: #1e3a8a;
        }
        .support-card p {
            font-size: 12px;
            color: #3b82f6;
            margin-top: 2px;
        }
        .support-contacts {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 13px;
            font-weight: 700;
            color: #1e40af;
        }

        /* Footer */
        .doc-footer {
            margin-top: 36px;
            border-top: 1px solid var(--border);
            padding-top: 20px;
            text-align: center;
            font-size: 11.5px;
            color: var(--muted);
            line-height: 1.6;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #fff;
                padding: 0;
                margin: 0;
            }
            .container {
                box-shadow: none;
                padding: 20px;
                max-width: 100%;
                border-radius: 0;
            }
            .no-print-bar { display: none !important; }
            .day-card, .split-box, .flight-card, .notes-box, .support-card {
                page-break-inside: avoid;
            }
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    </style>
</head>
<body>

    {{-- Top Action Bar (hidden in print) --}}
    <div class="no-print-bar">
        <div style="display: flex; gap: 8px;">
            <button class="btn btn-primary" onclick="window.print()">
                <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg>
                Print / Save as PDF
            </button>
            @php
                $invoiceUrl = auth('admin')->check() 
                    ? route('admin.bookings.invoice', $booking) 
                    : route('booking.invoice', $booking);
            @endphp
            <a href="{{ $invoiceUrl }}" class="btn btn-outline">
                <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                View Tax Invoice
            </a>
        </div>
        <button class="btn btn-outline" onclick="window.history.length > 1 ? window.history.back() : window.close()">
            ✕ Close
        </button>
    </div>

    <div class="container">
        {{-- Document Header --}}
        <header class="doc-header">
            <div>
                <div class="brand-logo">
                    {{ settings('company_name', 'Leemroz Travels') }}
                </div>
                <div class="brand-tagline">
                    {{ settings('company_tagline', 'Explore · Book · Experience') }}
                </div>
                <div class="company-meta">
                    {{ settings('company_address', 'Ishber Nishat Gupt Ganga, Srinagar J&K -190025') }}<br>
                    Helpline: {{ settings('company_phone', '+91 70069 76447') }}
                </div>
            </div>
            <div class="doc-title-box">
                <span class="doc-type-badge">
                    @if ($booking->product_type === 'package') Tour Voucher & Itinerary
                    @elseif ($booking->product_type === 'flight') Electronic Ticket & Itinerary
                    @elseif ($booking->product_type === 'hotel') Hotel Confirmation Voucher
                    @elseif ($booking->product_type === 'cab') Cab Travel Voucher
                    @else Official Travel Voucher
                    @endif
                </span>
                <div class="doc-title">Booking #{{ $booking->booking_reference }}</div>
                <div class="doc-meta">
                    Issue Date: <strong>{{ now()->format('d M Y') }}</strong><br>
                    Status: <strong style="color: {{ in_array($booking->status, ['confirmed', 'completed']) ? '#059669' : '#d97706' }}">{{ strtoupper(label_case($booking->status)) }}</strong>
                </div>
            </div>
        </header>

        {{-- Highlight Banner --}}
        @php
            $pkg = $booking->packageBooking?->package ?? \App\Models\Package::find($booking->product_id ?? $booking->packageBooking?->package_id);
            $flight = $booking->flight;
            $hotel = $booking->hotelBooking?->hotel ?? \App\Models\Hotel::find($booking->product_id ?? $booking->hotelBooking?->hotel_id);
            $hotelBooking = $booking->hotelBooking;
            $cab = $booking->cab;
            $vehicle = $cab?->vehicle ?? \App\Models\Vehicle::find($booking->product_id ?? $cab?->vehicle_id);
        @endphp

        <div class="highlight-banner">
            <div>
                @if ($booking->product_type === 'package')
                    <h2>{{ $pkg->name ?? $booking->items->first()?->name ?? 'Kashmir Tour Package' }}</h2>
                    <p>📍 {{ $pkg?->destination?->name ?? 'Kashmir' }} · {{ $pkg?->duration_days ?? 5 }} Days / {{ $pkg?->duration_nights ?? 4 }} Nights · {{ ucfirst($pkg?->package_type ?? 'Holiday') }} Tour</p>
                @elseif ($booking->product_type === 'flight')
                    <h2>Flight {{ $flight?->flight_number ?? $booking->items->first()?->name }} · {{ $flight?->journey['segments'][0]['from']['code'] ?? 'DEL' }} → {{ $flight?->journey['segments'][0]['to']['code'] ?? 'SXR' }}</h2>
                    <p>{{ $flight?->airline_code ?? 'Flight' }} · PNR: {{ $flight?->pnr ?? 'Confirmed' }} · {{ ucfirst($flight?->journey['cabin_class'] ?? 'Economy') }}</p>
                @elseif ($booking->product_type === 'hotel')
                    <h2>{{ $hotel?->name ?? $hotelBooking?->hotel_name ?? 'Hotel Stay' }}</h2>
                    <p>📍 {{ $hotel?->city ?? 'Srinagar' }} · {{ $hotelBooking?->room_type ?? 'Deluxe Room' }} · {{ $hotelBooking?->nights ?? 1 }} Night(s)</p>
                @elseif ($booking->product_type === 'cab')
                    <h2>{{ $vehicle?->name ?? $cab?->vehicle_name ?? 'Private Cab Transfer' }}</h2>
                    <p>📍 {{ $cab?->pickup_location }} ➔ {{ $cab?->drop_location ?? 'Destination' }} · {{ label_case($cab?->trip_type ?? 'one_way') }}</p>
                @endif
            </div>
            <span class="status-tag {{ in_array($booking->status, ['confirmed', 'completed']) ? '' : ($booking->status === 'cancelled' ? 'cancelled' : 'pending') }}">
                {{ label_case($booking->status) }}
            </span>
        </div>

        {{-- Overview Grid --}}
        <div class="overview-grid">
            <div class="overview-item">
                <h4>Primary Traveller</h4>
                <p>{{ $booking->contact['first_name'] ?? $booking->user?->name ?? 'Guest Traveller' }}</p>
                <small>{{ $booking->contact['email'] ?? $booking->user?->email }}</small>
                <small>{{ $booking->contact['phone'] ?? $booking->user?->phone }}</small>
            </div>
            <div class="overview-item">
                <h4>Travel Date</h4>
                <p>
                    @if ($booking->product_type === 'package')
                        {{ optional($booking->packageBooking?->departure_date)->format('d M Y') ?? 'Flexible' }}
                    @elseif ($booking->product_type === 'flight')
                        {{ \Carbon\Carbon::parse($flight?->journey['segments'][0]['from']['date'] ?? now())->format('d M Y') }}
                    @elseif ($booking->product_type === 'hotel')
                        {{ optional($hotelBooking?->check_in)->format('d M Y') }}
                    @elseif ($booking->product_type === 'cab')
                        {{ optional($cab?->pickup_datetime)->format('d M Y, h:i A') }}
                    @endif
                </p>
                <small>Booking Ref: {{ $booking->booking_reference }}</small>
            </div>
            <div class="overview-item">
                <h4>Guests / Party</h4>
                @if ($booking->product_type === 'package')
                    <p>{{ $booking->packageBooking?->adults ?? 1 }} Adult(s) @if($booking->packageBooking?->children > 0), {{ $booking->packageBooking->children }} Child(ren) @endif</p>
                    <small>{{ $booking->packageBooking?->room_count ?? 1 }} Room(s) reserved</small>
                @elseif ($booking->product_type === 'flight')
                    <p>{{ count($flight?->traveller_details ?? $booking->travellers) ?: 1 }} Passenger(s)</p>
                    <small>Seat: {{ $flight?->seat_selection['seat'] ?? 'Assigned at check-in' }}</small>
                @elseif ($booking->product_type === 'hotel')
                    <p>{{ $hotelBooking?->rooms ?? 1 }} Room(s) · {{ $hotelBooking?->nights ?? 1 }} Night(s)</p>
                    <small>Plan: {{ label_case($hotelBooking?->meal_plan ?? 'room_only') }}</small>
                @elseif ($booking->product_type === 'cab')
                    <p>{{ $vehicle?->capacity ?? '4-6' }} Seater</p>
                    <small>{{ $cab?->distance_km ? $cab->distance_km . ' km approx.' : 'Standard Route' }}</small>
                @endif
            </div>
            <div class="overview-item">
                <h4>Payment Status</h4>
                <p style="color: #059669;">{{ money($booking->total_amount) }}</p>
                <small>{{ strtoupper($booking->currency) }} · Fully Paid</small>
            </div>
        </div>

        {{-- Travellers List Table --}}
        @if ($booking->travellers && $booking->travellers->count() > 0)
            <div class="section-title">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                Travellers / Passenger Manifest
            </div>
            <table class="voucher-table">
                <thead>
                    <tr>
                        <th style="width: 8%">#</th>
                        <th style="width: 42%">Passenger Name</th>
                        <th style="width: 25%">Type</th>
                        <th style="width: 25%">Identity / Details</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($booking->travellers as $idx => $traveller)
                        <tr>
                            <td>{{ $idx + 1 }}</td>
                            <td>
                                <strong>{{ $traveller->full_name }}</strong>
                                @if($traveller->is_primary) <span style="font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; margin-left: 4px;">Primary</span> @endif
                            </td>
                            <td style="text-transform: capitalize;">{{ $traveller->traveller_type }}</td>
                            <td>{{ $traveller->id_type ? strtoupper($traveller->id_type) . ': ' . $traveller->id_number : ($traveller->dob ? 'DOB: ' . $traveller->dob->format('d M Y') : 'Standard') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- ========================================== --}}
        {{-- PRODUCT SPECIFIC: PACKAGE TOUR ITINERARY   --}}
        {{-- ========================================== --}}
        @if ($booking->product_type === 'package')
            @php $voucher = $booking->packageBooking?->voucher_data ?? []; @endphp
            @if ($pkg && $pkg->itineraries && $pkg->itineraries->count() > 0)
                <div class="section-title">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.253M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                    Day-by-Day Tour Itinerary
                </div>
                <div class="itinerary-timeline">
                    @foreach ($pkg->itineraries as $day)
                        @php $stay = $booking->hotelStayForDay($day->day_number) ?: $day->overnight_stay; @endphp
                        <div class="day-card">
                            <div class="day-header">
                                <span class="day-badge">Day {{ $day->day_number }}</span>
                                <h3 class="day-title">{{ $day->title }}</h3>
                                @if($stay)
                                    <span class="day-stay">🏨 Stay: {{ $stay }}</span>
                                @endif
                            </div>
                            <p class="day-description">{{ $day->description }}</p>
                            @if (!empty($day->meals) && count($day->meals))
                                <div class="day-footer">
                                    <span style="font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase;">Included Meals:</span>
                                    @foreach ($day->meals as $meal)
                                        <span class="meal-tag">✓ {{ $meal }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @elseif (! empty($voucher['itinerary']))
                {{-- Custom / CRM-quotation tour: no package template, render the snapshotted itinerary --}}
                <div class="section-title">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.253M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                    Day-by-Day Tour Itinerary
                </div>
                <div class="itinerary-timeline">
                    @foreach ($voucher['itinerary'] as $i => $day)
                        <div class="day-card">
                            <div class="day-header">
                                <span class="day-badge">Day {{ $day['day'] ?? ($i + 1) }}</span>
                                @if (! empty($day['title']))<h3 class="day-title">{{ $day['title'] }}</h3>@endif
                                @if (! empty($day['stay']))
                                    <span class="day-stay">🏨 Stay: {{ $day['stay'] }}</span>
                                @endif
                            </div>
                            @if (! empty($day['description']))<p class="day-description">{{ $day['description'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Accommodations: prefer the booked snapshot, fall back to the package template --}}
            @if ($booking->bookingHotels && $booking->bookingHotels->count() > 0)
                <div class="section-title">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205 3 1m1.5.5-1.5-.5"/></svg>
                    Your Hotel Stays
                </div>
                <table class="voucher-table">
                    <thead>
                        <tr>
                            <th>Stay / Location</th>
                            <th>Hotel</th>
                            <th>Room &amp; Meal</th>
                            <th>Check-in / out</th>
                            <th>Guests</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($booking->bookingHotels as $stay)
                            <tr>
                                <td><strong>{{ $stay->segment_label ?: 'Stay' }}</strong><br><span style="font-size:11px; color:var(--muted);">{{ $stay->nights }} Night(s)</span></td>
                                <td>
                                    <strong>{{ $stay->hotel_name_snapshot }}</strong>
                                    @if ($stay->star_rating_snapshot) <span style="color:#eab308;">{{ str_repeat('★', (int) $stay->star_rating_snapshot) }}</span>@endif
                                    @if ($stay->address_snapshot)<br><span style="font-size:11px; color:var(--muted);">{{ $stay->address_snapshot }}</span>@endif
                                    @unless ($stay->refundable)<br><span style="font-size:10px; color:#ef4444; font-weight:700;">Non-refundable</span>@endunless
                                </td>
                                <td>{{ $stay->room_name_snapshot ?: '—' }}<br><span style="font-size:11px; color:var(--muted);">{{ $stay->mealPlanLabel() }}</span></td>
                                <td>
                                    @if ($stay->check_in){{ $stay->check_in->format('d M') }} – {{ optional($stay->check_out)->format('d M Y') }}@else — @endif
                                    <br><span style="font-size:11px; color:var(--muted);">{{ $stay->rooms }} Room(s)</span>
                                </td>
                                <td>{{ $stay->guestSummary() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($booking->bookingHotels->whereNotNull('cancellation_policy_snapshot')->count())
                    <div class="notes-box">
                        <h4>Hotel Cancellation Policy</h4>
                        @foreach ($booking->bookingHotels->whereNotNull('cancellation_policy_snapshot') as $stay)
                            <p><strong>{{ $stay->hotel_name_snapshot }}:</strong> {{ $stay->cancellation_policy_snapshot }}</p>
                        @endforeach
                    </div>
                @endif
            @endif

            {{-- Fallback to the package's template hotel list when no snapshot exists --}}
            @if (! ($booking->bookingHotels && $booking->bookingHotels->count() > 0) && $pkg && $pkg->hotels && $pkg->hotels->count() > 0)
                <div class="section-title">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205 3 1m1.5.5-1.5-.5"/></svg>
                    Tour Accommodations
                </div>
                <table class="voucher-table">
                    <thead>
                        <tr>
                            <th>City / Location</th>
                            <th>Hotel / Houseboat</th>
                            <th>Room Type</th>
                            <th>Category</th>
                            <th>Nights</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pkg->hotels as $hotelItem)
                            <tr>
                                <td><strong>{{ $hotelItem->city }}</strong></td>
                                <td>{{ $hotelItem->hotel_name }}</td>
                                <td>{{ $hotelItem->room_type ?? 'Deluxe Room' }}</td>
                                <td><span style="font-size:11px; background:#f1f5f9; padding:2px 8px; border-radius:4px;">{{ $hotelItem->category ?? 'Standard' }}</span></td>
                                <td>{{ $hotelItem->nights ?? 1 }} Night(s)</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Flights --}}
            @if ($booking->packageFlights && $booking->packageFlights->count() > 0)
                <div class="section-title">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                    Your Flights
                </div>
                <table class="voucher-table">
                    <thead>
                        <tr><th>Flight</th><th>Route</th><th>Cabin / Trip</th><th>Travellers</th><th>Fare</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($booking->packageFlights as $pf)
                            <tr>
                                <td><strong>{{ $pf->label_snapshot }}</strong>@if ($pf->airline_snapshot)<br><span style="font-size:11px;color:var(--muted);">{{ $pf->airline_snapshot }}</span>@endif</td>
                                <td>{{ $pf->routeLabel() }}</td>
                                <td>{{ $pf->cabinLabel() }} · {{ ucfirst(str_replace('_',' ',$pf->trip_type)) }}<br><span style="font-size:11px;color:{{ $pf->refundable ? 'var(--emerald)' : '#ef4444' }};">{{ $pf->refundable ? 'Refundable' : 'Non-refundable' }}</span></td>
                                <td>{{ $pf->travellers }}</td>
                                <td><strong>{{ money($pf->price) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Private Transport / Cab attached to the package (e.g. CRM-quotation transfers) --}}
            @if ($cab)
                <div class="section-title">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.948c0-.621-.504-1.125-1.125-1.125H4.125C3.504 5.5 3 6.004 3 6.625v7.625m11.25-6.75h4.125c.621 0 1.125.504 1.125 1.125v4.5H3"/></svg>
                    Private Transport &amp; Transfers
                </div>
                <table class="voucher-table">
                    <tbody>
                        @if ($vehicle?->name || $cab->vehicle_name)
                            <tr>
                                <td style="width: 25%;"><strong>Vehicle</strong></td>
                                <td>{{ $vehicle?->name ?? $cab->vehicle_name }}@if ($vehicle?->capacity) · Capacity: {{ $vehicle->capacity }} Passengers @endif</td>
                            </tr>
                        @endif
                        @if ($cab->pickup_location)
                            <tr>
                                <td><strong>Pick-up</strong></td>
                                <td>{{ $cab->pickup_location }}</td>
                            </tr>
                        @endif
                        @if ($cab->drop_location)
                            <tr>
                                <td><strong>Drop-off</strong></td>
                                <td>{{ $cab->drop_location }}</td>
                            </tr>
                        @endif
                        @if ($cab->pickup_datetime)
                            <tr>
                                <td><strong>Pick-up Date</strong></td>
                                <td>{{ optional($cab->pickup_datetime)->format('d M Y') }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td><strong>Trip Type</strong></td>
                            <td><span style="background: #eff6ff; color: var(--brand); font-weight:700; padding:3px 8px; border-radius:4px;">{{ strtoupper(label_case($cab->trip_type ?? 'one_way')) }}</span></td>
                        </tr>
                    </tbody>
                </table>
            @endif

            {{-- Inclusions & Exclusions (package template, else CRM-quotation snapshot) --}}
            @php
                $inclusions = ($pkg && $pkg->inclusions && count($pkg->inclusions)) ? $pkg->inclusions : ($voucher['inclusions'] ?? []);
                $exclusions = ($pkg && $pkg->exclusions && count($pkg->exclusions)) ? $pkg->exclusions : ($voucher['exclusions'] ?? []);
            @endphp
            @if ((! empty($inclusions)) || (! empty($exclusions)))
                <div class="section-title">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    Tour Inclusions &amp; Exclusions
                </div>
                <div class="split-grid">
                    @if (! empty($inclusions))
                        <div class="split-box">
                            <h4 style="color: var(--emerald);"><span class="check-icon">✓</span> Inclusions</h4>
                            <ul>
                                @foreach ($inclusions as $inc)
                                    <li><span class="check-icon">✓</span> {{ is_array($inc) ? ($inc['title'] ?? reset($inc)) : $inc }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if (! empty($exclusions))
                        <div class="split-box">
                            <h4 style="color: #ef4444;"><span class="cross-icon">✕</span> Exclusions</h4>
                            <ul>
                                @foreach ($exclusions as $exc)
                                    <li><span class="cross-icon">✕</span> {{ is_array($exc) ? ($exc['title'] ?? reset($exc)) : $exc }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Highlights --}}
            @if ($pkg && $pkg->highlights && count($pkg->highlights))
                <div class="section-title">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/></svg>
                    Tour Highlights
                </div>
                <div class="split-box" style="margin-top: 12px;">
                    <ul style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 10px;">
                        @foreach ($pkg->highlights as $highlight)
                            <li><span class="check-icon">★</span> {{ $highlight }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Cancellation Policy --}}
            @if ($pkg && $pkg->cancellation_policy)
                <div class="notes-box">
                    <h4>Cancellation Policy</h4>
                    <p>{{ $pkg->cancellation_policy }}</p>
                </div>
            @endif
        @endif

        {{-- ========================================== --}}
        {{-- PRODUCT SPECIFIC: FLIGHT E-TICKET          --}}
        {{-- ========================================== --}}
        @if ($booking->product_type === 'flight')
            <div class="section-title">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                Flight Route &amp; Schedule
            </div>
            @php
                $segments = $flight?->journey['segments'] ?? [];
            @endphp
            @foreach ($segments as $s)
                <div class="flight-card">
                    <div style="display:flex; justify-content:space-between; margin-bottom:16px;">
                        <span class="flight-number-badge">
                            ✈ {{ $flight?->airline_code ?? 'Flight' }} {{ $s['flight_number'] ?? $flight?->flight_number }}
                        </span>
                        <span style="font-size:12px; font-weight:700; color:var(--muted); text-transform:uppercase;">
                            Class: {{ ucfirst($flight?->journey['cabin_class'] ?? 'Economy') }}
                        </span>
                    </div>
                    <div class="flight-route-row">
                        <div class="airport-block">
                            <div class="airport-code">{{ $s['from']['code'] ?? 'ORIGIN' }}</div>
                            <div class="airport-name">{{ $s['from']['name'] ?? 'Airport' }}</div>
                            <div class="airport-time">{{ $s['from']['time'] ?? 'Departure' }}</div>
                            <div style="font-size:11px; color:var(--muted); margin-top:2px;">{{ \Carbon\Carbon::parse($s['from']['date'] ?? now())->format('d M Y') }}</div>
                        </div>
                        <div class="flight-path">
                            <div style="font-size:11px; font-weight:600; color:var(--muted);">{{ $s['duration_minutes'] ? floor($s['duration_minutes']/60).'h '.($s['duration_minutes']%60).'m' : 'Non-Stop' }}</div>
                            <div class="flight-path-line">
                                <span class="flight-path-plane">✈</span>
                            </div>
                            <div style="font-size:11px; color:var(--emerald); font-weight:700;">Direct</div>
                        </div>
                        <div class="airport-block">
                            <div class="airport-code">{{ $s['to']['code'] ?? 'DEST' }}</div>
                            <div class="airport-name">{{ $s['to']['name'] ?? 'Airport' }}</div>
                            <div class="airport-time">{{ $s['to']['time'] ?? 'Arrival' }}</div>
                            <div style="font-size:11px; color:var(--muted); margin-top:2px;">{{ \Carbon\Carbon::parse($s['to']['date'] ?? now())->format('d M Y') }}</div>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="notes-box">
                <h4>Important Flight Notice &amp; Baggage Guidelines</h4>
                <ul style="margin-left: 18px; margin-top: 6px; list-style: disc;">
                    <li>Please report at the airport check-in counter at least <strong>2 hours before departure</strong> for domestic flights.</li>
                    <li>Carry a valid government-issued photo identity card (Aadhaar / Passport / Voter ID) for all passengers.</li>
                    <li>Cabin Baggage: 1 piece up to 7 kg per passenger. Check-in Baggage: 15 kg per passenger (unless extra baggage purchased).</li>
                    <li>Boarding gates close strictly 25 minutes prior to departure.</li>
                </ul>
            </div>
        @endif

        {{-- ========================================== --}}
        {{-- PRODUCT SPECIFIC: HOTEL VOUCHER            --}}
        {{-- ========================================== --}}
        @if ($booking->product_type === 'hotel')
            <div class="section-title">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205 3 1m1.5.5-1.5-.5"/></svg>
                Hotel Reservation Details
            </div>
            <table class="voucher-table">
                <tbody>
                    <tr>
                        <td style="width: 25%;"><strong>Hotel Property</strong></td>
                        <td style="width: 75%;">
                            <strong style="font-size: 15px;">{{ $hotel?->name ?? $hotelBooking?->hotel_name ?? 'Hotel' }}</strong>
                            @if($hotel?->star_rating) <span style="color:#eab308; margin-left: 6px;">{{ str_repeat('★', $hotel->star_rating) }}</span> @endif
                            <div style="color: var(--muted); font-size: 12.5px; margin-top: 2px;">{{ $hotel?->address ?? $hotel?->city ?? 'Kashmir' }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Check-In</strong></td>
                        <td><strong>{{ optional($hotelBooking?->check_in)->format('d M Y') }}</strong> (Standard Check-in: 02:00 PM)</td>
                    </tr>
                    <tr>
                        <td><strong>Check-Out</strong></td>
                        <td><strong>{{ optional($hotelBooking?->check_out)->format('d M Y') }}</strong> (Standard Check-out: 11:00 AM)</td>
                    </tr>
                    <tr>
                        <td><strong>Stay Duration</strong></td>
                        <td>{{ $hotelBooking?->nights ?? 1 }} Night(s) · {{ $hotelBooking?->rooms ?? 1 }} Room(s)</td>
                    </tr>
                    <tr>
                        <td><strong>Room Category</strong></td>
                        <td>{{ $hotelBooking?->room_type ?? 'Standard Deluxe' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Meal Plan</strong></td>
                        <td><span style="background: #ecfdf5; color: var(--emerald); font-weight:700; padding:3px 8px; border-radius:4px;">{{ strtoupper(label_case($hotelBooking?->meal_plan ?? 'room_only')) }}</span></td>
                    </tr>
                    @if($hotelBooking?->supplier_booking_id)
                        <tr>
                            <td><strong>Hotel Confirmation #</strong></td>
                            <td><strong style="color: var(--brand);">{{ $hotelBooking->supplier_booking_id }}</strong></td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <div class="notes-box">
                <h4>Hotel Check-in Instructions</h4>
                <p>Present this voucher along with Government ID proofs for all adult guests at the front desk upon arrival. Early check-in and late check-out are subject to room availability and hotel discretion.</p>
            </div>
        @endif

        {{-- ========================================== --}}
        {{-- PRODUCT SPECIFIC: CAB VOUCHER              --}}
        {{-- ========================================== --}}
        @if ($booking->product_type === 'cab')
            <div class="section-title">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.948c0-.621-.504-1.125-1.125-1.125H4.125C3.504 5.5 3 6.004 3 6.625v7.625m11.25-6.75h4.125c.621 0 1.125.504 1.125 1.125v4.5H3"/></svg>
                Cab &amp; Route Details
            </div>
            <table class="voucher-table">
                <tbody>
                    <tr>
                        <td style="width: 25%;"><strong>Vehicle Model</strong></td>
                        <td style="width: 75%;">
                            <strong style="font-size: 15px;">{{ $vehicle?->name ?? $cab?->vehicle_name ?? 'Cab' }}</strong>
                            <div style="color: var(--muted); font-size: 12px; margin-top: 2px;">{{ $vehicle?->model ?? 'Comfort Private Transfer' }} · Capacity: {{ $vehicle?->capacity ?? '4-6' }} Passengers</div>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Trip Type</strong></td>
                        <td><span style="background: #eff6ff; color: var(--brand); font-weight:700; padding:3px 8px; border-radius:4px;">{{ strtoupper(label_case($cab?->trip_type ?? 'one_way')) }}</span></td>
                    </tr>
                    <tr>
                        <td><strong>Pickup Location</strong></td>
                        <td><strong>{{ $cab?->pickup_location }}</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Drop Location</strong></td>
                        <td><strong>{{ $cab?->drop_location ?? 'As agreed with driver' }}</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Pickup Date &amp; Time</strong></td>
                        <td><strong style="color: var(--brand); font-size: 14.5px;">{{ optional($cab?->pickup_datetime)->format('d M Y, h:i A') }}</strong></td>
                    </tr>
                    @if($cab?->distance_km)
                        <tr>
                            <td><strong>Estimated Distance</strong></td>
                            <td>{{ $cab->distance_km }} km</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <div class="notes-box">
                <h4>Driver Coordination Notice</h4>
                <p>Your driver's name, phone number, and vehicle registration number will be dispatched via SMS/WhatsApp <strong>2 hours prior to your scheduled pickup time</strong>. Tolls, state taxes, and parking fees on the designated route are included in your voucher.</p>
            </div>
        @endif

        {{-- Emergency Helpline / Support Box --}}
        <div class="support-card">
            <div>
                <h4>24/7 {{ settings('company_name', 'Leemroz Travels') }} Traveler Assistance</h4>
                <p>Need on-trip help, directions, or timing coordination? Our local valley team is available around the clock.</p>
            </div>
            <div class="support-contacts">
                <div>📞 {{ settings('company_phone', '+91 70069 76447') }}</div>
                <div>✉ {{ settings('company_email', 'hello@leemroztravels.com') }}</div>
            </div>
        </div>

        {{-- Document Footer --}}
        <footer class="doc-footer">
            <p>This is an official system-generated travel voucher and itinerary issued by {{ settings('company_name', 'Leemroz Travels') }}.</p>
            <p>{{ settings('company_address', 'Ishber Nishat Gupt Ganga, Srinagar J&K -190025') }}</p>
            <p style="margin-top: 4px;">&copy; {{ date('Y') }} {{ settings('company_name', 'Leemroz Travels') }}. All rights reserved.</p>
        </footer>
    </div>

</body>
</html>

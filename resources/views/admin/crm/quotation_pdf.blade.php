<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Quotation {{ $quotation->quotation_number }} — Leemroz Travels</title>
    <style>
        @page {
            margin: 25px 30px;
        }
        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 12px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: top;
        }
        .logo-title {
            font-size: 22px;
            font-weight: bold;
            color: #0f766e;
            text-transform: uppercase;
            letter-spacing: -0.5px;
            margin: 0;
        }
        .tagline {
            font-size: 11px;
            color: #0d9488;
            font-weight: bold;
            margin-top: 2px;
        }
        .company-info {
            font-size: 10px;
            color: #64748b;
            line-height: 1.4;
            margin-top: 4px;
        }
        .doc-title {
            font-size: 18px;
            font-weight: bold;
            color: #0f172a;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-meta {
            font-size: 11px;
            color: #475569;
            text-align: right;
            margin-top: 4px;
        }
        .meta-pill {
            display: inline-block;
            background-color: #f1f5f9;
            padding: 3px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-weight: bold;
            color: #0f766e;
        }
        .divider {
            height: 2px;
            background-color: #0f766e;
            margin: 15px 0;
        }
        .section-heading {
            font-size: 13px;
            font-weight: bold;
            color: #0f766e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1.5px solid #ccfbf1;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 16px;
        }
        .grid-2 {
            width: 100%;
        }
        .grid-2 td {
            width: 50%;
            vertical-align: top;
            padding-right: 10px;
        }
        .label {
            color: #64748b;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: bold;
        }
        .value {
            color: #0f172a;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 6px;
        }
        .price-table {
            width: 100%;
            margin-top: 10px;
            margin-bottom: 16px;
        }
        .price-table th {
            background-color: #0f766e;
            color: #ffffff;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 10px;
            text-align: left;
        }
        .price-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
        }
        .price-table .total-row td {
            font-size: 13px;
            font-weight: bold;
            color: #0f766e;
            border-top: 2px solid #0f766e;
            border-bottom: 2px solid #0f766e;
            background-color: #f0fdfa;
        }
        .itinerary-day {
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #e2e8f0;
        }
        .day-badge {
            display: inline-block;
            background-color: #0f766e;
            color: #ffffff;
            font-weight: bold;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 3px;
            margin-right: 6px;
        }
        .day-title {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
        }
        .day-desc {
            font-size: 10.5px;
            color: #475569;
            margin-top: 3px;
            padding-left: 2px;
        }
        .footer {
            margin-top: 25px;
            padding-top: 10px;
            border-top: 1px solid #cbd5e1;
            font-size: 9.5px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <h1 class="logo-title">{{ settings('company_name', 'Leemroz Travels') }}</h1>
                <div class="tagline">{{ settings('company_tagline', 'Explore · Book · Experience') }}</div>
                <div class="company-info">
                    {{ settings('company_address', 'Ishber Nishat Gupt Ganga, Srinagar J&K -190025') }}<br>
                    Phone: {{ settings('company_phone', '+91 70069 76447') }} &nbsp;|&nbsp; Email: {{ settings('company_email', 'info@leemroztravels.com') }}
                    @if (settings('company_registration'))<br>Reg. No: {{ settings('company_registration') }}@endif
                </div>
            </td>
            <td style="width: 40%; text-align: right;">
                <div class="doc-title">Travel Quotation</div>
                <div class="doc-meta">
                    Ref: <span class="meta-pill">{{ $quotation->quotation_number }}</span><br>
                    Date: <strong>{{ $quotation->created_at->format('d M Y') }}</strong><br>
                    @if ($quotation->valid_until)
                        Valid Until: <strong style="color: #b45309;">{{ $quotation->valid_until->format('d M Y') }}</strong>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    {{-- Client & Trip Summary Grid --}}
    <div class="card">
        <table class="grid-2">
            <tr>
                <td>
                    <div class="label">Prepared For (Client):</div>
                    <div class="value">{{ $lead->name ?? 'Valued Traveller' }}</div>
                    <div style="font-size: 11px; color: #475569;">
                        Phone: <strong>{{ $lead->phone ?? 'N/A' }}</strong><br>
                        Email: <strong>{{ $lead->email ?? 'N/A' }}</strong>
                    </div>
                </td>
                <td>
                    <div class="label">Proposal & Journey Scope:</div>
                    <div class="value">{{ $quotation->title }}</div>
                    <div style="font-size: 11px; color: #475569;">
                        Destination: <strong>{{ $lead->destination ?? ($package->destination?->name ?? 'Kashmir') }}</strong><br>
                        @php($paxSummary = $quotation->paxSummary())
                        @if ($paxSummary)
                            Travellers: <strong>{{ $paxSummary }}</strong> &nbsp;|&nbsp;
                        @elseif ($lead->travellers_count)
                            Travellers: <strong>{{ $lead->travellers_count }} Person(s)</strong> &nbsp;|&nbsp;
                        @endif
                        @if ($lead->travel_date)
                            Tentative Date: <strong>{{ $lead->travel_date->format('d M Y') }}</strong>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Stay & Transfer (optional hotels / cab / pickup / dropoff) --}}
    @php($hotelStays = $quotation->resolvedHotels())
    @php($vehicle = $quotation->vehicle)
    @if (! empty($hotelStays) || $vehicle || $quotation->pickup_location || $quotation->dropoff_location)
        <div class="section-heading">Stay &amp; Transfer Details</div>
        <div class="card">
            @if (! empty($hotelStays))
                <div class="label">Hotels / Accommodation:</div>
                <table class="price-table" style="margin-top: 6px; margin-bottom: 10px;">
                    <thead>
                        <tr>
                            <th style="width: 8%;">#</th>
                            <th style="width: 42%;">Hotel</th>
                            <th style="width: 35%;">Location</th>
                            <th style="width: 15%; text-align: right;">Nights</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hotelStays as $i => $stay)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td><strong>{{ $stay['hotel_name'] ?? '—' }}</strong>@if (! empty($stay['city'])) <span style="color:#64748b;">· {{ $stay['city'] }}</span>@endif</td>
                                <td>{{ $stay['location'] ?? '—' }}</td>
                                <td style="text-align: right;">{{ isset($stay['nights']) && $stay['nights'] !== null ? $stay['nights'] : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <table class="grid-2">
                <tr>
                    <td>
                        @if ($vehicle)
                            <div class="label">Cab / Vehicle:</div>
                            <div class="value">{{ $vehicle->name }}@if ($vehicle->type) ({{ $vehicle->type->name }})@endif</div>
                            @if ($vehicle->passenger_capacity)<div style="font-size: 10.5px; color: #475569;">Seats up to {{ $vehicle->passenger_capacity }} passengers</div>@endif
                        @endif
                    </td>
                    <td>
                        @if ($quotation->pickup_location)
                            <div class="label">Pick-up Location:</div>
                            <div class="value">{{ $quotation->pickup_location }}</div>
                        @endif
                        @if ($quotation->dropoff_location)
                            <div class="label" style="margin-top: 6px;">Drop-off Location:</div>
                            <div class="value">{{ $quotation->dropoff_location }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    @endif

    {{-- Package Description if attached --}}
    @if ($package)
        <div class="section-heading">Holiday Package Overview</div>
        <div style="margin-bottom: 14px; font-size: 11px; color: #334155;">
            <strong>{{ $package->name }}</strong> ({{ $package->duration_days }} Days / {{ $package->duration_nights }} Nights)<br>
            {{ $package->short_description ?? 'A curated Kashmir experience including transfers, accommodation, and guided excursions.' }}
        </div>
    @endif

    {{-- Proposed Daily Itinerary (quote's own, else the linked package's) --}}
    @php($itineraryDays = $quotation->resolvedItinerary())
    @if (! empty($itineraryDays))
        <div class="section-heading">Proposed Daily Itinerary</div>
        <div style="margin-bottom: 16px;">
            @foreach ($itineraryDays as $i => $day)
                <div class="itinerary-day">
                    <div>
                        <span class="day-badge">Day {{ $day['day'] ?? ($i + 1) }}</span>
                        @if (! empty($day['title']))<span class="day-title">{{ $day['title'] }}</span>@endif
                    </div>
                    @if (! empty($day['description']))<div class="day-desc">{{ $day['description'] }}</div>@endif
                    @if (! empty($day['stay']))<div class="day-desc" style="margin-top: 2px;"><strong style="color: #0f766e;">Overnight:</strong> {{ $day['stay'] }}</div>@endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Price Breakdown Table --}}
    <div class="section-heading">Commercial Proposal & Pricing</div>
    <table class="price-table">
        <thead>
            <tr>
                <th style="width: 70%;">Description / Service Scope</th>
                <th style="width: 30%; text-align: right;">Amount (INR)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $quotation->title }}</strong><br>
                    <span style="font-size: 10px; color: #64748b;">Includes accommodation, transportation, transfers, and package services as quoted.</span>
                </td>
                <td style="text-align: right; font-weight: bold;">₹{{ number_format((float)$quotation->subtotal, 2) }}</td>
            </tr>
            @if ((float)$quotation->tax_amount > 0)
            <tr>
                <td>Goods & Services Tax (GST 5%)</td>
                <td style="text-align: right;">₹{{ number_format((float)$quotation->tax_amount, 2) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td>Grand Total (Inclusive of All Applicable Taxes):</td>
                <td style="text-align: right; font-size: 14px;">₹{{ number_format((float)$quotation->total_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Inclusions & Exclusions --}}
    @php($incl = $quotation->resolvedInclusions())
    @php($excl = $quotation->resolvedExclusions())
    @if ($incl || $excl)
        <table class="grid-2" style="margin-top: 4px;">
            <tr>
                @if ($incl)
                    <td style="vertical-align: top; padding-right: 10px;">
                        <div class="label" style="color: #047857;">Inclusions</div>
                        <ul style="margin: 4px 0 0 16px; padding: 0; font-size: 11px; color: #1f2937;">
                            @foreach ($incl as $item)<li style="margin-bottom: 3px;">{{ is_array($item) ? ($item['title'] ?? reset($item)) : $item }}</li>@endforeach
                        </ul>
                    </td>
                @endif
                @if ($excl)
                    <td style="vertical-align: top; padding-left: 10px;">
                        <div class="label" style="color: #b91c1c;">Exclusions</div>
                        <ul style="margin: 4px 0 0 16px; padding: 0; font-size: 11px; color: #1f2937;">
                            @foreach ($excl as $item)<li style="margin-bottom: 3px;">{{ is_array($item) ? ($item['title'] ?? reset($item)) : $item }}</li>@endforeach
                        </ul>
                    </td>
                @endif
            </tr>
        </table>
    @endif

    {{-- Dedicated Consultant & Booking Info --}}
    <div class="card" style="background-color: #f0fdfa; border-color: #a7f3d0;">
        <table class="grid-2">
            <tr>
                <td>
                    <div class="label" style="color: #047857;">Your Dedicated Consultant:</div>
                    <div class="value" style="color: #065f46;">{{ settings('company_legal_name', 'Leemroz Travels Private Limited') }}</div>
                    <div style="font-size: 10.5px; color: #047857;">
                        Email: {{ settings('company_email', 'info@leemroztravels.com') }}<br>
                        Phone: {{ settings('company_phone', '+91 70069 76447') }}
                    </div>
                </td>
                <td>
                    <div class="label" style="color: #047857;">How to Confirm This Quotation:</div>
                    <div style="font-size: 10.5px; color: #064e3b; margin-top: 4px;">
                        To confirm your dates and lock in current hotel/cab pricing, simply reply to this quotation or call your consultant. A 25% token deposit confirms your booking.
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Terms --}}
    <div style="font-size: 9.5px; color: #64748b; margin-top: 10px;">
        <strong>Terms & Conditions:</strong> Pricing is subject to availability at the time of deposit payment. Rates are valid until {{ $quotation->valid_until ? $quotation->valid_until->format('d M Y') : '14 days from issue' }}. Inclusions and cancellations are governed by standard OTA booking policies.
    </div>

    {{-- Footer --}}
    <div class="footer">
        {{ settings('company_legal_name', settings('company_name', 'Leemroz Travels')) }} · {{ settings('company_address', 'Ishber Nishat Gupt Ganga, Srinagar J&K -190025') }}{{ settings('website_url') ? ' · ' . settings('website_url') : '' }}{{ settings('company_registration') ? ' · Reg. No: ' . settings('company_registration') : '' }}<br>
        This is a computer-generated travel quotation document.
    </div>
</body>
</html>

@extends('pdf.layout')

@section('pdf_title', 'Tax Invoice ' . $booking->booking_reference)

@section('foot_path')/admin/bookings/{{ $booking->id }}@endsection

@section('pdf_content')
    @section('doc_badge')
        <span class="doc-label blue">TAX INVOICE</span>
    @endsection
    @section('doc_right')
        <div class="doc-line">Invoice Date: <strong style="color:#0f172a">{{ now()->format('d M Y') }}</strong></div>
        <div class="doc-line">Booking Reference: <strong class="blue">{{ $booking->booking_reference }}</strong></div>
        <div class="doc-line" style="margin-top:6px">
            @if ($booking->status === 'confirmed')
                <span class="status-green">CONFIRMED</span>
            @elseif ($booking->status === 'completed')
                <span class="status-green">COMPLETED</span>
            @else
                <span class="status-amber">{{ strtoupper(str_replace('_', ' ', $booking->status)) }}</span>
            @endif
        </div>
    @endsection

    @include('pdf.partials.brand-header')

    <table class="w">
        <tr>
            <td class="vtop" width="50%">
                <p class="lbl">Billed To (Customer Details)</p>
                <p class="val big">{{ $booking->contact['first_name'] ?? ($booking->user?->name ?? 'Guest') }}{{ isset($booking->contact['last_name']) && $booking->contact['last_name'] ? ' ' . $booking->contact['last_name'] : '' }}</p>
                <p class="muted">{{ $booking->contact['email'] ?? $booking->user?->email }}</p>
                <p class="muted">Phone: {{ $booking->contact['phone'] ?? $booking->user?->phone }}</p>
            </td>
            <td class="vtop doc-right" width="50%">
                <p class="lbl">Service Provider (Issued By)</p>
                <p class="val big">{{ settings('company_name', 'Leemroz Travels') }}</p>
                <p class="muted">{{ settings('company_address') }}</p>
                <p class="muted">{{ settings('company_email') }}</p>
                @if (settings('company_registration'))<p class="muted">Reg. No: {{ settings('company_registration') }}</p>@endif
            </td>
        </tr>
    </table>

    {{-- Product summary box --}}
    <div class="card" style="margin-top:16px">
        <table class="w">
            <tr>
                <td class="vtop">
                    @if ($booking->product_type === 'package')
                        <p class="val">Package: <span class="blue">{{ $booking->packageBooking?->package_name ?? 'Tour Package' }}</span></p>
                        <p class="muted" style="margin-top:4px">
                            Departure: <strong style="color:#0f172a">{{ optional($booking->packageBooking?->departure_date)->format('d M Y') }}</strong>
                            &nbsp;·&nbsp; Duration: <strong style="color:#0f172a">{{ $booking->packageBooking?->package?->duration_days ?? '' }}D / {{ $booking->packageBooking?->package?->duration_nights ?? '' }}N</strong>
                            @if ($booking->packageBooking?->package?->destination)
                                &nbsp;·&nbsp; Destination: <strong style="color:#0f172a">{{ $booking->packageBooking->package->destination->name }}</strong>
                            @endif
                        </p>
                    @elseif ($booking->product_type === 'hotel')
                        <p class="val">Hotel: <span class="blue">{{ $booking->hotelBooking?->hotel_name ?? '—' }}</span></p>
                        <p class="muted" style="margin-top:4px">
                            Check-in: <strong style="color:#0f172a">{{ optional($booking->hotelBooking?->check_in)->format('d M Y') }}</strong>
                            &nbsp;·&nbsp; Check-out: <strong style="color:#0f172a">{{ optional($booking->hotelBooking?->check_out)->format('d M Y') }}</strong>
                            &nbsp;·&nbsp; {{ $booking->hotelBooking?->rooms ?? 1 }} Room(s)
                        </p>
                    @elseif ($booking->product_type === 'cab')
                        <p class="val">Cab: <span class="blue">{{ $booking->cab?->vehicle_name ?? '—' }}</span></p>
                        <p class="muted" style="margin-top:4px">
                            Pickup: <strong style="color:#0f172a">{{ optional($booking->cab?->pickup_datetime)->format('d M Y, h:i A') }}</strong>
                            &nbsp;·&nbsp; {{ $booking->cab?->pickup_location }} → {{ $booking->cab?->drop_location }}
                        </p>
                    @elseif ($booking->product_type === 'flight')
                        <p class="val">Flight: <span class="blue">{{ $booking->flight?->airline_code }}-{{ $booking->flight?->flight_number }}</span></p>
                        @php($seg = $booking->flight?->journey['segments'][0] ?? [])
                        <p class="muted" style="margin-top:4px">
                            {{ $seg['from']['city'] ?? '' }} ({{ $seg['from']['code'] ?? '' }}) → {{ $seg['to']['city'] ?? '' }} ({{ $seg['to']['code'] ?? '' }})
                            &nbsp;·&nbsp; Depart: <strong style="color:#0f172a">{{ \Carbon\Carbon::parse($seg['from']['date'] ?? now())->format('d M Y') }}</strong>
                        </p>
                    @endif
                </td>
                <td class="vtop doc-right" width="160">
                    <span class="pill">{{ ucfirst($booking->product_type) }} Booking</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- Hotel accommodation (package bookings with hotels) --}}
    @if ($booking->product_type === 'package' && $booking->bookingHotels->count())
        <h2 class="sec with-icon">Accommodation</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Hotel &amp; Stay</th>
                    <th style="width:120px">Room / Meal</th>
                    <th style="width:150px">Dates</th>
                    <th style="width:90px" class="right">Charge</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($booking->bookingHotels as $hotel)
                    <tr>
                        <td>
                            <strong>{{ $hotel->hotel_name_snapshot }}</strong>@if ($hotel->star_rating_snapshot) <span style="color:#f59e0b">{{ str_repeat('★', (int) $hotel->star_rating_snapshot) }}</span>@endif
                            @if ($hotel->segment_label)<br><span class="muted">{{ $hotel->segment_label }}</span>@endif
                        </td>
                        <td>{{ $hotel->room_name_snapshot ?: '—' }}<br><span class="muted">{{ $hotel->mealPlanLabel() }}</span></td>
                        <td>
                            @if ($hotel->check_in){{ $hotel->check_in->format('d M') }} – {{ optional($hotel->check_out)->format('d M Y') }}<br>@endif
                            <span class="muted">{{ $hotel->nights }}N · {{ $hotel->rooms }} Room(s)</span>
                        </td>
                        <td class="right">{{ $hotel->is_included ? 'Included' : money($hotel->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Items --}}
    <table class="data" style="margin-top:16px">
        <thead>
            <tr>
                <th>Item &amp; Description</th>
                <th style="width:60px">Qty</th>
                <th style="width:110px" class="right">Unit Rate</th>
                <th style="width:110px" class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($booking->items as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td class="right">{{ money($item->unit_price) }}</td>
                    <td class="right">{{ money($item->total_price) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="w" style="margin-top:16px">
        <tr>
            <td class="vtop" width="45%">
                <div class="card">
                    <p class="lbl">Payment Information</p>
                    @php($paid = $booking->payments->firstWhere('status', 'captured'))
                    <p class="muted" style="margin-top:6px">Gateway: <strong style="color:#0f172a">{{ $paid ? strtoupper($paid->gateway) : '—' }}</strong></p>
                    @if ($paid?->gateway_payment_id)
                        <p class="muted">Payment ID: <strong style="color:#0f172a">{{ $paid->gateway_payment_id }}</strong></p>
                    @endif
                    @if ($paid?->paid_at)
                        <p class="muted">Paid At: <strong style="color:#0f172a">{{ $paid->paid_at->format('d M Y, h:i A') }}</strong></p>
                    @endif
                    <p class="muted">
                        Status:
                        @if ($paid)
                            <strong class="green">Payment Verified &amp; Captured</strong>
                        @else
                            <strong class="status-amber">Payment Pending</strong>
                        @endif
                    </p>
                    <p class="lbl" style="margin-top:10px">Passengers / Guests</p>
                    <p class="muted">{{ $booking->travellers->pluck('full_name')->implode(', ') ?: ($booking->contact['first_name'] ?? 'Guest') }}</p>
                </div>
            </td>
            <td class="vtop">
                <div class="totals">
                    <div class="row">Subtotal <span style="float:right">{{ money($booking->subtotal) }}</span></div>
                    <div class="row">Taxes &amp; GST <span style="float:right">{{ money($booking->tax_amount) }}</span></div>
                    @if ($booking->discount_amount > 0)
                        <div class="row green">Coupon Discount <span style="float:right">-{{ money($booking->discount_amount) }}</span></div>
                    @endif
                    <div class="row grand">Total Amount Paid <span style="float:right">{{ money($booking->total_amount) }}</span></div>
                </div>
            </td>
        </tr>
    </table>

    <p class="terms">
        <strong>Terms &amp; Conditions</strong>
        {{ settings('invoice_terms', 'Thank you for booking with ' . settings('company_name', 'us') . '. This is a system generated invoice.') }}
        <br>Support Helpline: {{ settings('company_phone') }} · Email: {{ settings('company_email') }}
    </p>
@endsection

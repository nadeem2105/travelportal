@extends('pdf.layout')

@section('pdf_title', 'Tour Itinerary & Voucher ' . $booking->booking_reference)

@section('foot_path')/booking/{{ $booking->booking_reference }}/itinerary

@section('pdf_content')
    @section('doc_badge')
        <span class="doc-label blue">TOUR VOUCHER &amp; ITINERARY</span>
    @endsection
    @section('doc_right')
        <div class="doc-big">Booking #{{ $booking->booking_reference }}</div>
        <div class="doc-line">Issue Date: <strong style="color:#0f172a">{{ now()->format('d M Y') }}</strong></div>
        <div class="doc-line">Status:
            @if ($booking->status === 'confirmed' || $booking->status === 'completed')
                <span class="status-green">CONFIRMED</span>
            @else
                <span class="status-amber">{{ strtoupper(str_replace('_', ' ', $booking->status)) }}</span>
            @endif
        </div>
    @endsection

    @include('pdf.partials.brand-header')

    {{-- Package card --}}
    <div class="card">
        <table class="w">
            <tr>
                <td class="vtop">
                    <p class="val big blue">{{ $booking->packageBooking?->package_name ?? ($package->name ?? 'Kashmir Tour') }}</p>
                    <p class="muted" style="margin-top:4px">
                        @if ($package?->destination) {{ $package->destination->name }} · @endif
                        {{ $package->duration_days ?? '' }} Days / {{ $package->duration_nights ?? '' }} Nights
                        · {{ ucfirst($package->package_type ?? 'Group') }} Tour
                    </p>
                </td>
                <td class="vtop doc-right" width="110">
                    @if ($booking->status === 'confirmed' || $booking->status === 'completed')
                        <span class="pill green">CONFIRMED</span>
                    @else
                        <span class="pill gray">{{ strtoupper(str_replace('_', ' ', $booking->status)) }}</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- Summary card --}}
    <div class="card">
        <table class="w">
            <tr>
                <td class="vtop" width="34%">
                    <p class="lbl">Primary Traveller</p>
                    <p class="val">{{ $booking->travellers->first()?->full_name ?? ($booking->contact['first_name'] ?? 'Guest') }}</p>
                    <p class="muted">{{ $booking->contact['email'] ?? '' }}</p>
                    <p class="muted">{{ $booking->contact['phone'] ?? '' }}</p>
                </td>
                <td class="vtop" width="33%">
                    <p class="lbl">Travel Date</p>
                    <p class="val">{{ optional($booking->packageBooking?->departure_date)->format('d M Y') }}</p>
                    <p class="muted">Booking Ref: {{ $booking->booking_reference }}</p>
                </td>
                <td class="vtop">
                    <p class="lbl">Guests / Party</p>
                    <p class="val">{{ $booking->packageBooking?->adults ?? 1 }} Adult(s)@if ($booking->packageBooking?->children) + {{ $booking->packageBooking->children }} Child(ren)@endif</p>
                    <p class="muted">{{ $booking->packageBooking?->room_count ?? 1 }} Room(s) reserved</p>
                </td>
            </tr>
            <tr><td colspan="3" style="height:12px"></td></tr>
            <tr>
                <td class="vtop" colspan="3">
                    <p class="lbl">Payment Status</p>
                    <p class="val green" style="font-size:15px">{{ money($booking->total_amount) }}</p>
                    <p class="muted">{{ strtoupper($booking->currency) }}
                        @if ($booking->payments->where('status', 'captured')->count()) · Fully Paid @else · Payment Pending @endif
                    </p>
                </td>
            </tr>
        </table>
    </div>

    {{-- Passenger manifest --}}
    <h2 class="sec with-icon">Travellers / Passenger Manifest</h2>
    <table class="data">
        <thead>
            <tr>
                <th style="width:36px">#</th>
                <th>Passenger Name</th>
                <th style="width:90px">Type</th>
                <th style="width:140px">Identity / Details</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($booking->travellers as $i => $traveller)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        <strong>{{ $traveller->full_name }}</strong>
                        @if ($traveller->is_primary) <span class="pill" style="margin-left:4px">Primary</span> @endif
                    </td>
                    <td>{{ ucfirst($traveller->traveller_type) }}</td>
                    <td>{{ $traveller->id_type ? ucfirst(str_replace('_', ' ', $traveller->id_type)) . ': ' . $traveller->id_number : 'Standard' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Accommodation / hotel stays --}}
    @if ($booking->bookingHotels->count())
        <h2 class="sec with-icon">Your Hotel Stays</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Stay</th>
                    <th>Hotel</th>
                    <th style="width:120px">Room / Meal</th>
                    <th style="width:140px">Check-in / out</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($booking->bookingHotels as $hotel)
                    <tr>
                        <td>{{ $hotel->segment_label ?: 'Stay' }}<br><span class="muted">{{ $hotel->nights }} Night(s)</span></td>
                        <td><strong>{{ $hotel->hotel_name_snapshot }}</strong>@if ($hotel->star_rating_snapshot) <span style="color:#f59e0b">{{ str_repeat('★', (int) $hotel->star_rating_snapshot) }}</span>@endif
                            @if ($hotel->address_snapshot)<br><span class="muted">{{ $hotel->address_snapshot }}</span>@endif
                        </td>
                        <td>{{ $hotel->room_name_snapshot ?: '—' }}<br><span class="muted">{{ $hotel->mealPlanLabel() }}</span></td>
                        <td>
                            @if ($hotel->check_in){{ $hotel->check_in->format('d M') }} – {{ optional($hotel->check_out)->format('d M Y') }}@else — @endif
                            <br><span class="muted">{{ $hotel->rooms }} Room(s)</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Flights --}}
    @if ($booking->packageFlights->count())
        <h2 class="sec with-icon">Your Flights</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Flight</th>
                    <th style="width:120px">Route</th>
                    <th style="width:110px">Cabin / Trip</th>
                    <th style="width:80px" class="right">Fare</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($booking->packageFlights as $pf)
                    <tr>
                        <td><strong>{{ $pf->label_snapshot }}</strong>@if ($pf->airline_snapshot)<br><span class="muted">{{ $pf->airline_snapshot }}</span>@endif</td>
                        <td>{{ $pf->routeLabel() }}<br><span class="muted">{{ $pf->travellers }} pax</span></td>
                        <td>{{ $pf->cabinLabel() }}<br><span class="muted">{{ ucfirst(str_replace('_',' ',$pf->trip_type)) }}</span></td>
                        <td class="right">{{ money($pf->price) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Day-wise plan --}}
    <h2 class="sec with-icon">Day-by-Day Tour Itinerary</h2>
    @foreach ($package?->itineraries ?? [] as $day)
        @php($stay = $booking->hotelStayForDay($day->day_number) ?: $day->overnight_stay)
        <div class="card">
            <table class="w">
                <tr>
                    <td class="vtop">
                        <span class="pill">DAY {{ $day->day_number }}</span>
                        <strong style="font-size:13px; margin-left:6px">{{ $day->title }}</strong>
                    </td>
                    <td class="vtop doc-right" width="140">
                        @if ($stay)
                            <span class="pill gray">Stay: {{ $stay }}</span>
                        @endif
                    </td>
                </tr>
            </table>
            <p class="muted" style="margin:8px 0 4px 0; line-height:1.55">{{ $day->description }}</p>
            @if ($day->meals)
                <p>
                    <span class="lbl" style="margin-right:6px">Included Meals:</span>
                    @foreach ($day->meals as $meal)
                        <span class="pill green" style="margin-right:4px">{{ $meal }}</span>
                    @endforeach
                </p>
            @endif
        </div>
    @endforeach

    {{-- Inclusions / exclusions --}}
    <h2 class="sec with-icon">What's Included</h2>
    <table class="data">
        <tr><td>{{ implode(' · ', $package->inclusions ?? []) }}</td></tr>
    </table>
    @if ($package->exclusions)
        <h2 class="sec with-icon">Not Included</h2>
        <table class="data">
            <tr><td>{{ implode(' · ', $package->exclusions ?? []) }}</td></tr>
        </table>
    @endif

    @if ($package->cancellation_policy)
        <div class="note" style="border:1px solid #e2e8f0; background:#f8fafc; padding:10px 12px; font-size:10.5px; color:#475569; border-radius:6px; margin-top:16px">
            <strong>Cancellation Policy:</strong> {{ $package->cancellation_policy }}
        </div>
    @endif
@endsection

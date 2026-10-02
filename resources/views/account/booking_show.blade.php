@extends('layouts.site')

@section('page')
<x-account.shell>
    <a href="{{ route('account.trips') }}" class="text-sm text-ink-500 hover:text-brand-700">← Back to My Trips</a>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold">{{ $booking->booking_reference }}</h1>
            <p class="text-xs text-ink-500 capitalize">Booked {{ optional($booking->created_at)->format('d M Y, h:i A') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="status-pill {{ status_pill_class($booking->status) }}">{{ label_case($booking->status) }}</span>
            <a href="{{ route('account.booking.invoice', $booking) }}" target="_blank" class="btn-ghost btn-sm inline-flex items-center gap-1 border border-slate-200">
                <svg class="h-3.5 w-3.5 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                Invoice
            </a>
            <a href="{{ route('account.booking.itinerary', $booking) }}" target="_blank" class="btn-primary btn-sm inline-flex items-center gap-1">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/></svg>
                @if ($booking->product_type === 'package') Tour Itinerary
                @elseif ($booking->product_type === 'flight') E-Ticket
                @elseif ($booking->product_type === 'hotel') Hotel Voucher
                @elseif ($booking->product_type === 'cab') Cab Voucher
                @else Travel Voucher
                @endif
            </a>
            @php($pdfType = match ($booking->product_type) {
                'flight' => 'ticket',
                'hotel', 'cab' => 'voucher',
                'package' => 'itinerary',
                default => 'invoice',
            })
            <a href="{{ route('account.booking.invoice-pdf', ['booking' => $booking, 'type' => $pdfType]) }}"
               class="btn-ghost btn-sm inline-flex items-center gap-1 border border-slate-200" download>
                <svg class="h-3.5 w-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                Download PDF
            </a>
            <a href="{{ route('account.booking.invoice-pdf', ['booking' => $booking, 'type' => 'invoice']) }}"
               class="btn-ghost btn-sm inline-flex items-center gap-1 border border-slate-200" download>
                <svg class="h-3.5 w-3.5 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                Invoice PDF
            </a>
        </div>
    </div>

    {{-- Multi-Modal Visual Trip Timeline --}}
    <div class="card mt-5 p-6 overflow-hidden">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h2 class="font-display text-base font-bold text-ink-900 flex items-center gap-2">
                <svg class="h-5 w-5 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                Trip Journey &amp; Milestones
            </h2>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $booking->status === 'confirmed' || $booking->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                Status: {{ label_case($booking->status) }}
            </span>
        </div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4 relative">
            @if ($booking->product_type === 'package')
                {{-- Step 1 --}}
                <div class="flex md:flex-col items-start gap-3 bg-slate-50/70 p-3.5 rounded-xl border border-slate-100">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white shadow-sm">1</span>
                    <div>
                        <p class="text-xs font-bold text-ink-900">Booking Confirmed</p>
                        <p class="text-[11px] text-ink-500">Ref: {{ $booking->booking_reference }}</p>
                    </div>
                </div>
                {{-- Step 2 --}}
                <div class="flex md:flex-col items-start gap-3 bg-slate-50/70 p-3.5 rounded-xl border border-slate-100">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white shadow-sm">2</span>
                    <div>
                        <p class="text-xs font-bold text-ink-900">Arrival &amp; Pickup</p>
                        <p class="text-[11px] text-ink-500">Departure: {{ optional($booking->packageBooking?->departure_date)->format('d M Y') ?? 'Tailored date' }}</p>
                    </div>
                </div>
                {{-- Step 3 --}}
                <div class="flex md:flex-col items-start gap-3 bg-slate-50/70 p-3.5 rounded-xl border border-slate-100">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white shadow-sm">3</span>
                    <div>
                        <p class="text-xs font-bold text-ink-900">Tour &amp; Sightseeing</p>
                        <p class="text-[11px] text-ink-500">{{ $booking->packageBooking?->package?->duration_days ?? '5' }} Days / {{ $booking->packageBooking?->package?->duration_nights ?? '4' }} Nights</p>
                    </div>
                </div>
                {{-- Step 4 --}}
                <div class="flex md:flex-col items-start gap-3 bg-slate-50/70 p-3.5 rounded-xl border border-slate-100">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-300 text-xs font-bold text-ink-700 shadow-sm">4</span>
                    <div>
                        <p class="text-xs font-bold text-ink-900">Return &amp; Memories</p>
                        <p class="text-[11px] text-ink-500">Airport / Station drop-off</p>
                    </div>
                </div>
            @elseif ($booking->product_type === 'flight')
                <div class="flex md:flex-col items-start gap-3 bg-slate-50/70 p-3.5 rounded-xl border border-slate-100">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white shadow-sm">1</span>
                    <div>
                        <p class="text-xs font-bold text-ink-900">E-Ticket Confirmed</p>
                        <p class="text-[11px] text-ink-500">PNR: {{ $booking->flight?->pnr ?? 'Awaiting' }}</p>
                    </div>
                </div>
                <div class="flex md:flex-col items-start gap-3 bg-slate-50/70 p-3.5 rounded-xl border border-slate-100">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white shadow-sm">2</span>
                    <div>
                        <p class="text-xs font-bold text-ink-900">Airport Check-in</p>
                        <p class="text-[11px] text-ink-500">Report 2 hrs prior to departure</p>
                    </div>
                </div>
                <div class="flex md:flex-col items-start gap-3 bg-slate-50/70 p-3.5 rounded-xl border border-slate-100">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white shadow-sm">3</span>
                    <div>
                        <p class="text-xs font-bold text-ink-900">Flight Journey</p>
                        <p class="text-[11px] text-ink-500">{{ $booking->flight?->airline_name ?? 'Scheduled flight' }}</p>
                    </div>
                </div>
                <div class="flex md:flex-col items-start gap-3 bg-slate-50/70 p-3.5 rounded-xl border border-slate-100">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-300 text-xs font-bold text-ink-700 shadow-sm">4</span>
                    <div>
                        <p class="text-xs font-bold text-ink-900">Arrival &amp; Baggage</p>
                        <p class="text-[11px] text-ink-500">Destination airport claim</p>
                    </div>
                </div>
            @elseif ($booking->product_type === 'hotel')
                <div class="flex md:flex-col items-start gap-3 bg-slate-50/70 p-3.5 rounded-xl border border-slate-100">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white shadow-sm">1</span>
                    <div>
                        <p class="text-xs font-bold text-ink-900">Voucher Issued</p>
                        <p class="text-[11px] text-ink-500">Instant reservation confirmed</p>
                    </div>
                </div>
                <div class="flex md:flex-col items-start gap-3 bg-slate-50/70 p-3.5 rounded-xl border border-slate-100">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white shadow-sm">2</span>
                    <div>
                        <p class="text-xs font-bold text-ink-900">Hotel Check-in</p>
                        <p class="text-[11px] text-ink-500">{{ optional($booking->hotelBooking?->check_in)->format('d M Y') ?? 'Check-in day' }} (2:00 PM)</p>
                    </div>
                </div>
                <div class="flex md:flex-col items-start gap-3 bg-slate-50/70 p-3.5 rounded-xl border border-slate-100">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white shadow-sm">3</span>
                    <div>
                        <p class="text-xs font-bold text-ink-900">Stay &amp; Amenities</p>
                        <p class="text-[11px] text-ink-500">{{ $booking->hotelBooking?->rooms ?? 1 }} Room(s) · {{ ucfirst(str_replace('_', ' ', $booking->hotelBooking?->meal_plan ?? 'room only')) }}</p>
                    </div>
                </div>
                <div class="flex md:flex-col items-start gap-3 bg-slate-50/70 p-3.5 rounded-xl border border-slate-100">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-300 text-xs font-bold text-ink-700 shadow-sm">4</span>
                    <div>
                        <p class="text-xs font-bold text-ink-900">Check-out</p>
                        <p class="text-[11px] text-ink-500">{{ optional($booking->hotelBooking?->check_out)->format('d M Y') ?? 'Check-out day' }} (11:00 AM)</p>
                    </div>
                </div>
            @else
                <div class="flex md:flex-col items-start gap-3 bg-slate-50/70 p-3.5 rounded-xl border border-slate-100">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white shadow-sm">1</span>
                    <div>
                        <p class="text-xs font-bold text-ink-900">Booking Confirmed</p>
                        <p class="text-[11px] text-ink-500">Cab reserved</p>
                    </div>
                </div>
                <div class="flex md:flex-col items-start gap-3 bg-slate-50/70 p-3.5 rounded-xl border border-slate-100">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white shadow-sm">2</span>
                    <div>
                        <p class="text-xs font-bold text-ink-900">Driver Dispatched</p>
                        <p class="text-[11px] text-ink-500">Details sent 2 hrs prior</p>
                    </div>
                </div>
                <div class="flex md:flex-col items-start gap-3 bg-slate-50/70 p-3.5 rounded-xl border border-slate-100">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white shadow-sm">3</span>
                    <div>
                        <p class="text-xs font-bold text-ink-900">Pickup</p>
                        <p class="text-[11px] text-ink-500">{{ optional($booking->cab?->pickup_at)->format('d M, h:i A') ?? 'Scheduled time' }}</p>
                    </div>
                </div>
                <div class="flex md:flex-col items-start gap-3 bg-slate-50/70 p-3.5 rounded-xl border border-slate-100">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-300 text-xs font-bold text-ink-700 shadow-sm">4</span>
                    <div>
                        <p class="text-xs font-bold text-ink-900">Destination Drop</p>
                        <p class="text-[11px] text-ink-500">Safe arrival</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_320px]">
        <div class="space-y-5">
            <div class="card p-6">
                <h2 class="font-display text-lg font-bold capitalize">{{ $booking->product_type }} Details</h2>
                <div class="mt-3 space-y-2 text-sm">
                    @foreach ($booking->items as $item)
                        <div class="flex justify-between"><span class="text-ink-700">{{ $item->name }} @if($item->quantity > 1) × {{ $item->quantity }} @endif</span><span>{{ money($item->total_price) }}</span></div>
                    @endforeach
                </div>

                @if ($booking->product_type === 'flight' && $booking->flight)
                    <div class="divider my-4"></div>
                    <p class="text-sm"><strong>PNR:</strong> {{ $booking->flight->pnr ?? 'Awaiting issuance' }}
                        @if ($booking->flight->ticket_number) · <strong>Ticket:</strong> {{ $booking->flight->ticket_number }} @endif</p>
                @elseif ($booking->product_type === 'cab' && $booking->cab)
                    <div class="divider my-4"></div>
                    @if (!empty($booking->cab->driver_details['driver_name']))
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-emerald-800">Assigned Driver &amp; Vehicle</p>
                            <div class="mt-2 grid sm:grid-cols-2 gap-2 text-sm text-ink-900">
                                <p><strong>Driver:</strong> {{ $booking->cab->driver_details['driver_name'] }}</p>
                                <p><strong>Phone:</strong> <a href="tel:{{ $booking->cab->driver_details['driver_phone'] }}" class="text-brand-600 font-bold underline">{{ $booking->cab->driver_details['driver_phone'] }}</a></p>
                                <p><strong>Vehicle No:</strong> {{ $booking->cab->driver_details['vehicle_number'] }}</p>
                                @if (!empty($booking->cab->driver_details['vendor_name']))
                                    <p><strong>Fleet:</strong> {{ $booking->cab->driver_details['vendor_name'] }}</p>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-ink-600">
                            <p class="font-semibold text-ink-800">Driver Assignment in Progress</p>
                            <p class="text-xs text-ink-500 mt-1">Driver and vehicle plate details will be updated here and sent via SMS 2–4 hours prior to your scheduled pickup.</p>
                        </div>
                    @endif
                @elseif ($booking->product_type === 'hotel' && $booking->hotelBooking)
                    <div class="divider my-4"></div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-ink-800 space-y-1">
                        <p class="font-bold text-ink-900">{{ $booking->hotelBooking->hotel_name }}</p>
                        <p class="text-xs text-ink-600">Room: <strong>{{ $booking->hotelBooking->room_type }}</strong> · Meal Plan: <strong>{{ ucfirst($booking->hotelBooking->meal_plan ?? 'room only') }}</strong></p>
                        <p class="text-xs text-ink-500">Check-in: {{ $booking->hotelBooking->check_in?->format('d M Y') }} · Check-out: {{ $booking->hotelBooking->check_out?->format('d M Y') }} ({{ $booking->hotelBooking->nights }} Nights, {{ $booking->hotelBooking->rooms }} Rooms)</p>
                    </div>
                @endif
            </div>

            @if ($booking->product_type === 'package' && $booking->bookingHotels->count())
                <div class="card p-6">
                    <h2 class="font-display text-lg font-bold flex items-center gap-2">
                        <svg class="h-5 w-5 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M3 7v14m18-14v14M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16M9 7h1m-1 4h1m4-4h1m-1 4h1"/></svg>
                        Hotel Stay
                    </h2>
                    <div class="mt-3 space-y-3">
                        @foreach ($booking->bookingHotels as $hotel)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-bold text-ink-900">
                                            {{ $hotel->hotel_name_snapshot }}
                                            @if ($hotel->star_rating_snapshot)<span class="ml-1 text-xs text-amber-500">{{ str_repeat('★', (int) $hotel->star_rating_snapshot) }}</span>@endif
                                        </p>
                                        @if ($hotel->segment_label)<p class="text-xs text-ink-500">{{ $hotel->segment_label }}</p>@endif
                                        @if ($hotel->address_snapshot)<p class="text-xs text-ink-500">{{ $hotel->address_snapshot }}</p>@endif
                                    </div>
                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $hotel->refundable ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-600' }}">
                                        {{ $hotel->refundable ? 'Refundable' : 'Non-refundable' }}
                                    </span>
                                </div>
                                <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-1.5 text-xs sm:grid-cols-4">
                                    <div><dt class="text-ink-500">Room</dt><dd class="font-semibold text-ink-900">{{ $hotel->room_name_snapshot ?: '—' }}</dd></div>
                                    <div><dt class="text-ink-500">Meal Plan</dt><dd class="font-semibold text-ink-900">{{ $hotel->mealPlanLabel() }}</dd></div>
                                    <div><dt class="text-ink-500">Check-in</dt><dd class="font-semibold text-ink-900">{{ optional($hotel->check_in)->format('d M Y') ?? '—' }}</dd></div>
                                    <div><dt class="text-ink-500">Check-out</dt><dd class="font-semibold text-ink-900">{{ optional($hotel->check_out)->format('d M Y') ?? '—' }}</dd></div>
                                    <div><dt class="text-ink-500">Nights</dt><dd class="font-semibold text-ink-900">{{ $hotel->nights }}</dd></div>
                                    <div><dt class="text-ink-500">Rooms</dt><dd class="font-semibold text-ink-900">{{ $hotel->rooms }}</dd></div>
                                    <div class="col-span-2"><dt class="text-ink-500">Guests</dt><dd class="font-semibold text-ink-900">{{ $hotel->guestSummary() }}</dd></div>
                                </dl>
                                @if ($hotel->cancellation_policy_snapshot)
                                    <p class="mt-2 border-t border-slate-200 pt-2 text-[11px] text-ink-500"><strong>Cancellation:</strong> {{ $hotel->cancellation_policy_snapshot }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ route('account.booking.invoice-pdf', ['booking' => $booking, 'type' => 'voucher']) }}"
                       class="btn-ghost btn-sm mt-4 inline-flex items-center gap-1 border border-slate-200" download>
                        Download Hotel Voucher
                    </a>
                </div>
            @endif

            @if ($booking->product_type === 'package' && $booking->packageFlights->count())
                <div class="card p-6">
                    <h2 class="font-display text-lg font-bold flex items-center gap-2">
                        <svg class="h-5 w-5 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                        Flights
                    </h2>
                    <div class="mt-3 space-y-3">
                        @foreach ($booking->packageFlights as $pf)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-ink-900">{{ $pf->label_snapshot }}</p>
                                        <p class="text-xs text-ink-500">{{ $pf->routeLabel() }}@if ($pf->airline_snapshot) · {{ $pf->airline_snapshot }}@endif</p>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $pf->refundable ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-600' }}">
                                        {{ $pf->refundable ? 'Refundable' : 'Non-refundable' }}
                                    </span>
                                </div>
                                <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-1.5 text-xs sm:grid-cols-4">
                                    <div><dt class="text-ink-500">Cabin</dt><dd class="font-semibold text-ink-900">{{ $pf->cabinLabel() }}</dd></div>
                                    <div><dt class="text-ink-500">Trip</dt><dd class="font-semibold text-ink-900">{{ ucfirst(str_replace('_',' ',$pf->trip_type)) }}</dd></div>
                                    <div><dt class="text-ink-500">Travellers</dt><dd class="font-semibold text-ink-900">{{ $pf->travellers }}</dd></div>
                                    @if ($pf->baggage_snapshot)<div><dt class="text-ink-500">Baggage</dt><dd class="font-semibold text-ink-900">{{ $pf->baggage_snapshot }}</dd></div>@endif
                                </dl>
                                @if ($pf->cancellation_policy_snapshot)
                                    <p class="mt-2 border-t border-slate-200 pt-2 text-[11px] text-ink-500"><strong>Cancellation:</strong> {{ $pf->cancellation_policy_snapshot }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($booking->travellers->count())
                <div class="card p-6">
                    <h2 class="font-display text-lg font-bold">Travellers</h2>
                    <div class="mt-3 space-y-2 text-sm">
                        @foreach ($booking->travellers as $traveller)
                            <div class="flex justify-between">
                                <span>{{ $traveller->full_name }} <span class="text-xs text-ink-500">({{ $traveller->traveller_type }})</span></span>
                                <span class="text-xs text-ink-500">{{ $traveller->id_type ? $traveller->id_type . ': ' . $traveller->id_number : '' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($booking->refunds->count())
                <div class="card p-6">
                    <h2 class="font-display text-lg font-bold">Refund Status</h2>
                    @foreach ($booking->refunds as $refund)
                        <div class="mt-2 flex justify-between text-sm">
                            <span><span class="status-pill {{ status_pill_class($refund->status) }}">{{ ucfirst($refund->status) }}</span></span>
                            <span class="font-bold">{{ money($refund->amount) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <aside class="h-fit space-y-4">
            <div class="card p-6">
                <h2 class="font-display text-lg font-bold">Payment Summary</h2>
                <div class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-ink-500">Subtotal</span><span>{{ money($booking->subtotal) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-500">Taxes &amp; Fees</span><span>{{ money($booking->tax_amount) }}</span></div>
                    @if ($booking->discount_amount > 0)
                        <div class="flex justify-between text-emerald-600"><span>Discount</span><span>-{{ money($booking->discount_amount) }}</span></div>
                    @endif
                    <div class="divider"></div>
                    <div class="flex justify-between font-extrabold"><span>Total</span><span>{{ money($booking->total_amount) }}</span></div>
                </div>
                @if ($booking->status === 'payment_pending')
                    <a href="{{ route('checkout.show', $booking) }}" class="btn-primary btn-md mt-4 w-full">Complete Payment</a>
                @endif
            </div>

            @if ($booking->isCancellable())
                <div class="card p-6" x-data="{ open: false }">
                    <h2 class="font-display text-lg font-bold">Cancel Booking</h2>
                    <p class="mt-1 text-xs text-ink-500">Cancellation is subject to the supplier's policy and may incur charges.</p>
                    <button class="btn-ghost btn-md mt-3 w-full !text-rose-600" @click="open = !open">Request Cancellation</button>
                    <form x-cloak x-show="open" action="{{ route('account.booking.cancel', $booking) }}" method="POST" class="mt-3 space-y-2">
                        @csrf
                        <textarea name="reason" class="input" rows="3" placeholder="Reason for cancellation (min 10 characters)" required></textarea>
                        <button class="btn-danger btn-md w-full">Submit Request</button>
                    </form>
                </div>
            @endif

            <a href="{{ route('support.index') }}" class="btn-ghost btn-md block w-full text-center">Need Help? Contact Support</a>
        </aside>
    </div>
</x-account.shell>
@endsection

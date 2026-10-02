@extends('layouts.site')

@section('page')
<section class="shell max-w-2xl py-14">
    <div class="card overflow-hidden">
        <div class="p-10 text-center">
            @if ($booking->status === 'confirmed')
                <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                    <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                </span>
                <h1 class="font-display mt-5 text-3xl font-bold text-ink-900">Booking Confirmed!</h1>
                <p class="mt-2 text-sm text-ink-500">Thank you, {{ $booking->contact['first_name'] ?? 'traveller' }}! A confirmation has been sent to {{ $booking->contact['email'] ?? 'your email' }}.</p>
            @elseif ($booking->status === 'payment_success_booking_failed')
                <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                    <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                </span>
                <h1 class="font-display mt-5 text-2xl font-bold text-ink-900">Payment Received — Booking in Process</h1>
                <p class="mt-2 text-sm text-ink-500">Your payment succeeded but we're still confirming with the supplier. Our team is on it — you'll receive confirmation shortly. No action needed.</p>
            @elseif ($booking->status === 'payment_pending')
                <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-amber-100 text-amber-600">⏳</span>
                <h1 class="font-display mt-5 text-2xl font-bold text-ink-900">Payment Pending</h1>
                <p class="mt-2 text-sm text-ink-500">Your booking is reserved. Complete the payment to confirm.</p>
                <a href="{{ route('checkout.show', $booking) }}" class="btn-primary btn-md mt-4">Complete Payment</a>
            @else
                <h1 class="font-display mt-5 text-2xl font-bold text-ink-900">Booking {{ label_case($booking->status) }}</h1>
            @endif
        </div>

        <div class="border-t border-slate-100 bg-slate-50/60 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wider text-ink-500">Booking Reference</p>
                    <p class="font-display text-lg font-bold text-ink-900">{{ $booking->booking_reference }}</p>
                </div>
                <div>
                    @php $isPaid = in_array($booking->status, ['confirmed', 'completed', 'payment_success_booking_failed']); @endphp
                    <p class="text-xs uppercase tracking-wider text-ink-500">{{ $isPaid ? 'Amount Paid' : 'Amount Payable' }}</p>
                    <p class="font-display text-lg font-bold text-ink-900">{{ money($booking->total_amount) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-ink-500">Travel Date</p>
                    <p class="text-sm font-bold text-ink-900">
                        @if ($booking->product_type === 'flight') {{ \Carbon\Carbon::parse($booking->flight?->journey['segments'][0]['from']['date'] ?? now())->format('d M Y') }}
                        @elseif ($booking->product_type === 'hotel') {{ optional($booking->hotelBooking?->check_in)->format('d M Y') }}
                        @elseif ($booking->product_type === 'cab') {{ optional($booking->cab?->pickup_datetime)->format('d M Y') }}
                        @elseif ($booking->product_type === 'package') {{ optional($booking->packageBooking?->departure_date)->format('d M Y') }}
                        @endif
                    </p>
                </div>
            </div>

            @if ($booking->product_type === 'flight' && $booking->flight?->pnr)
                <div class="mt-4 rounded-xl bg-white p-4 ring-1 ring-slate-200">
                    <p class="text-xs text-ink-500">Airline PNR</p>
                    <p class="font-display text-lg font-bold tracking-widest text-brand-700">{{ $booking->flight->pnr }}</p>
                </div>
            @endif

            @if ($booking->product_type === 'package' && $booking->bookingHotels->count())
                <div class="mt-4 rounded-xl bg-white p-4 text-left ring-1 ring-slate-200">
                    <p class="text-xs uppercase tracking-wider text-ink-500">Your Hotel{{ $booking->bookingHotels->count() > 1 ? 's' : '' }}</p>
                    <div class="mt-2 space-y-2">
                        @foreach ($booking->bookingHotels as $hotel)
                            <div class="text-sm">
                                <p class="font-bold text-ink-900">
                                    {{ $hotel->hotel_name_snapshot }}
                                    @if ($hotel->star_rating_snapshot)<span class="text-xs text-amber-500">{{ str_repeat('★', (int) $hotel->star_rating_snapshot) }}</span>@endif
                                    @if ($hotel->segment_label)<span class="text-xs font-normal text-ink-500">· {{ $hotel->segment_label }}</span>@endif
                                </p>
                                <p class="text-xs text-ink-500">
                                    {{ $hotel->room_name_snapshot ?: '' }} · {{ $hotel->mealPlanLabel() }}
                                    @if ($hotel->check_in) · {{ $hotel->check_in->format('d M') }}–{{ optional($hotel->check_out)->format('d M Y') }}@endif
                                    · {{ $hotel->rooms }} room(s)
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($booking->product_type === 'package' && $booking->packageFlights->count())
                <div class="mt-4 rounded-xl bg-white p-4 text-left ring-1 ring-slate-200">
                    <p class="text-xs uppercase tracking-wider text-ink-500">Your Flight{{ $booking->packageFlights->count() > 1 ? 's' : '' }}</p>
                    <div class="mt-2 space-y-2">
                        @foreach ($booking->packageFlights as $pf)
                            <div class="text-sm">
                                <p class="font-bold text-ink-900">✈ {{ $pf->label_snapshot }}</p>
                                <p class="text-xs text-ink-500">{{ $pf->routeLabel() }} · {{ $pf->cabinLabel() }} · {{ $pf->travellers }} traveller(s)</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mt-6 flex flex-wrap justify-center gap-3">
                @auth('web')
                    <a href="{{ route('account.booking.show', $booking) }}" class="btn-primary btn-md">View My Trip</a>
                @endauth
                @if ($booking->product_type === 'package' && $booking->bookingHotels->count())
                    <a href="{{ route('booking.pdf', ['booking' => $booking, 'type' => 'voucher']) }}" class="btn-ghost btn-md inline-flex items-center gap-1.5 border border-slate-200 hover:bg-slate-50" download>
                        <svg class="h-4 w-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M3 7v14m18-14v14M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/></svg>
                        Hotel Voucher
                    </a>
                @endif
                <a href="{{ route('booking.invoice', $booking) }}" target="_blank" class="btn-ghost btn-md inline-flex items-center gap-1.5 border border-slate-200 hover:bg-slate-50">
                    <svg class="h-4 w-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                    View Invoice
                </a>
                <a href="{{ route('booking.pdf', ['booking' => $booking, 'type' => 'invoice']) }}" class="btn-ghost btn-md inline-flex items-center gap-1.5 border border-slate-200 hover:bg-slate-50" download>
                    <svg class="h-4 w-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                    Invoice PDF
                </a>
                <a href="{{ route('booking.itinerary', $booking) }}" target="_blank" class="btn-primary btn-md inline-flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/></svg>
                    @if ($booking->product_type === 'package') Download Itinerary
                    @elseif ($booking->product_type === 'flight') Download E-Ticket
                    @elseif ($booking->product_type === 'hotel') Download Hotel Voucher
                    @elseif ($booking->product_type === 'cab') Download Cab Voucher
                    @else Download Voucher
                    @endif
                </a>
                <a href="{{ route('home') }}" class="btn-ghost btn-md">Back to Home</a>
            </div>
        </div>
    </div>
</section>

@if (in_array($booking->status, ['confirmed', 'completed', 'payment_success_booking_failed']))
    <x-track-event event="purchase" fb="Purchase" :data="[
        'currency' => 'INR',
        'value' => (float) $booking->total_amount,
        'transaction_id' => $booking->booking_reference,
        'content_type' => $booking->product_type,
    ]" />
@endif
@endsection

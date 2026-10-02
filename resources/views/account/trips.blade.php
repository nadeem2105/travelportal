@extends('layouts.site')

@section('page')
<x-account.shell :accountNav="'trips'">
    <h1 class="font-display text-2xl font-bold">My Trips</h1>

    <div class="mt-4 flex flex-wrap gap-2">
        @foreach (['' => 'All', 'confirmed' => 'Confirmed', 'payment_pending' => 'Payment Pending', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded'] as $key => $label)
            <a href="{{ route('account.trips', array_filter(['status' => $key])) }}"
               class="rounded-full px-4 py-1.5 text-xs font-semibold transition {{ ($status ?? '') === $key ? 'bg-brand-600 text-white' : 'bg-white text-ink-700 ring-1 ring-slate-200 hover:text-brand-700' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="mt-5 space-y-4">
        @forelse ($bookings as $booking)
            <article class="card flex flex-wrap items-center justify-between gap-4 p-5">
                <div>
                    <div class="flex items-center gap-2">
                        <p class="font-display font-bold text-ink-900">{{ $booking->booking_reference }}</p>
                        <span class="status-pill {{ status_pill_class($booking->status) }}">{{ label_case($booking->status) }}</span>
                    </div>
                    <p class="mt-0.5 text-xs capitalize text-ink-500">{{ $booking->product_type }} · Booked {{ optional($booking->created_at)->format('d M Y') }}</p>
                </div>
                <div class="text-right">
                    <p class="font-display text-lg font-extrabold">{{ money($booking->total_amount) }}</p>
                    <div class="mt-1 flex flex-wrap justify-end gap-1.5">
                        <a href="{{ route('account.booking.show', $booking) }}" class="btn-ghost btn-sm !py-1.5 !text-xs">View</a>
                        <a href="{{ route('account.booking.invoice', $booking) }}" target="_blank" class="btn-ghost btn-sm !py-1.5 !text-xs">Invoice</a>
                        <a href="{{ route('account.booking.itinerary', $booking) }}" target="_blank" class="btn-ghost btn-sm !py-1.5 !text-xs font-semibold text-brand-600">
                            @if ($booking->product_type === 'package') Itinerary
                            @elseif ($booking->product_type === 'flight') E-Ticket
                            @else Voucher
                            @endif
                        </a>
                    </div>
                </div>
            </article>
        @empty
            <div class="card p-12 text-center">
                <p class="font-display font-bold">No trips found</p>
                <a href="{{ route('packages.index') }}" class="btn-primary btn-md mt-4">Plan Your Trip</a>
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $bookings->links() }}</div>
</x-account.shell>
@endsection

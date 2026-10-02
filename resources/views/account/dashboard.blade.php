@extends('layouts.site')
@section('accountNav', 'dashboard')

@section('page')
<x-account.shell :accountNav="'dashboard'" :unread="auth('web')->user()->unreadNotificationsCount()">
    <h1 class="font-display text-2xl font-bold">My Dashboard</h1>

    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            'Total Bookings' => $stats['total'],
            'Upcoming Trips' => $stats['upcoming'],
            'Completed Trips' => $stats['completed'],
            'Unread Alerts' => $stats['unread'],
        ] as $label => $value)
            <div class="card p-5">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-500">{{ $label }}</p>
                <p class="font-display mt-1 text-3xl font-extrabold text-brand-600">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="card mt-6 p-6">
        <div class="flex items-center justify-between">
            <h2 class="font-display text-lg font-bold">Recent Bookings</h2>
            <a href="{{ route('account.trips') }}" class="text-sm font-bold text-brand-600 hover:text-brand-800">View All →</a>
        </div>
        <div class="mt-4 space-y-3">
            @forelse ($bookings as $booking)
                <a href="{{ route('account.booking.show', $booking) }}" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-100 p-4 transition hover:border-brand-200 hover:bg-brand-50/40">
                    <div>
                        <p class="text-sm font-bold text-ink-900">{{ $booking->booking_reference }}</p>
                        <p class="text-xs capitalize text-ink-500">{{ $booking->product_type }} · {{ optional($booking->booked_at)->format('d M Y') }}</p>
                    </div>
                    <span class="status-pill {{ status_pill_class($booking->status) }}">{{ label_case($booking->status) }}</span>
                    <p class="font-display font-bold">{{ money($booking->total_amount) }}</p>
                </a>
            @empty
                <p class="text-sm text-ink-500">No bookings yet. <a href="{{ route('packages.index') }}" class="font-bold text-brand-600">Explore packages →</a></p>
            @endforelse
        </div>
    </div>
</x-account.shell>
@endsection

@extends('layouts.admin')
@section('pageTitle', 'Customer: ' . $user->name)

@section('content')
    <a href="{{ route('admin.customers.index') }}" class="text-sm text-ink-500 hover:text-brand-700">← All Customers</a>
    <h1 class="font-display mt-2 text-xl font-bold">{{ $user->name }}</h1>
    <p class="text-sm text-ink-500">{{ $user->email }} · {{ $user->phone ?? 'No phone' }} · Joined {{ $user->created_at->format('d M Y') }}</p>

    <div class="admin-card mt-4 overflow-x-auto">
        <h2 class="font-display text-base font-bold">Bookings ({{ $user->bookings_count }})</h2>
        <table class="admin-table mt-2">
            <thead><tr><th>Reference</th><th>Type</th><th>Status</th><th class="text-right">Amount</th><th class="text-right">Date</th></tr></thead>
            <tbody>
                @forelse ($user->bookings as $booking)
                    <tr>
                        <td><a href="{{ route('admin.bookings.show', $booking) }}" class="font-bold text-brand-600">{{ $booking->booking_reference }}</a></td>
                        <td class="capitalize">{{ $booking->product_type }}</td>
                        <td><span class="status-pill {{ status_pill_class($booking->status) }}">{{ label_case($booking->status) }}</span></td>
                        <td class="text-right font-bold">{{ money($booking->total_amount) }}</td>
                        <td class="text-right text-xs text-ink-500">{{ $booking->created_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-ink-500">No bookings</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

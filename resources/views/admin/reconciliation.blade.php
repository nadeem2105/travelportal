@extends('layouts.admin')
@section('pageTitle', 'Reconciliation')

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="font-display text-xl font-bold">Reconciliation Dashboard</h1>
        <span class="badge-soft">Payment received · supplier booking failed</span>
    </div>
    <p class="mt-1 text-sm text-ink-500">
        These bookings captured customer payment but the supplier could not confirm. Action options: retry confirmation
        (update status to <strong>pending</strong>, then re-run), or cancel with 0% penalty to refund the customer.
        No auto-refund is issued — business rules apply.
    </p>

    <x-admin.filters
        :action="route('admin.reconciliation')"
        :search="false"
        :filters="[
            ['name' => 'type', 'label' => 'Type', 'options' => ['flight' => 'Flight', 'hotel' => 'Hotel', 'cab' => 'Cab', 'package' => 'Package']],
        ]"
        :count="$cases->total()" />

    <div class="admin-card mt-4 overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th>Booking</th><th>Product</th><th>Customer</th><th>Supplier</th><th>Amount Paid</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
                @forelse ($cases as $booking)
                    <tr>
                        <td><a href="{{ route('admin.bookings.show', $booking) }}" class="font-bold text-brand-600">{{ $booking->booking_reference }}</a></td>
                        <td class="capitalize">{{ $booking->product_type }}</td>
                        <td>{{ $booking->contact['email'] ?? $booking->user?->email ?? 'Guest' }}</td>
                        <td>{{ $booking->supplier?->name ?? '—' }}</td>
                        <td class="font-bold">{{ money($booking->total_amount) }}</td>
                        <td class="text-right">
                            @can('edit_booking')
                                <form action="{{ route('admin.bookings.status', $booking) }}" method="POST" class="inline">
                                    @csrf
                                    <input type="hidden" name="status" value="pending">
                                    <button class="btn-primary btn-sm">Retry Booking</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-ink-500">No reconciliation cases. 🎉</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $cases->links() }}</div>
@endsection

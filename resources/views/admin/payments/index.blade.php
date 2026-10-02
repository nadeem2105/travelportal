@extends('layouts.admin')
@section('pageTitle', 'Payments')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-xl font-bold">Payments</h1>
        <a href="{{ route('admin.payments.export', request()->query()) }}" class="btn-ghost btn-md">Export CSV</a>
    </div>

    @php
        $gateways = \App\Models\PaymentGateway::orderBy('sort_order')->pluck('name', 'code')->all();
    @endphp

    <x-admin.filters
        :action="route('admin.payments.index')"
        search-placeholder="Search order ID, payment ID, booking ref…"
        :filters="[
            ['name' => 'status', 'label' => 'Status', 'options' => ['created' => 'Created', 'authorized' => 'Authorized', 'captured' => 'Captured', 'failed' => 'Failed', 'refunded' => 'Refunded', 'partially_refunded' => 'Partially Refunded']],
            ['name' => 'gateway', 'label' => 'Gateway', 'options' => $gateways],
        ]"
        :count="$payments->total()">
        <input type="date" name="from" value="{{ request('from') }}" class="input !w-auto" title="From date">
        <input type="date" name="to" value="{{ request('to') }}" class="input !w-auto" title="To date">
    </x-admin.filters>

    <div class="admin-card mt-4 overflow-x-auto">
        <table class="admin-table">
            <thead><tr>
                <th>Booking</th>
                <th>Gateway</th>
                <th>Order ID</th>
                <th>Payment ID</th>
                <x-admin.sort-header column="status" label="Status" />
                <th>Method</th>
                <x-admin.sort-header column="amount" label="Amount" align="right" />
                <x-admin.sort-header column="created_at" label="Date" align="right" />
            </tr></thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td><a href="{{ route('admin.bookings.show', $payment->booking) }}" class="font-bold text-brand-600">{{ $payment->booking->booking_reference }}</a></td>
                        <td class="capitalize">{{ $payment->gateway }}</td>
                        <td class="text-xs">{{ $payment->gateway_order_id }}</td>
                        <td class="text-xs">{{ $payment->gateway_payment_id ?? '—' }}</td>
                        <td><span class="status-pill {{ status_pill_class($payment->status) }}">{{ ucfirst($payment->status) }}</span></td>
                        <td class="capitalize">{{ $payment->method ?? '—' }}</td>
                        <td class="text-right font-bold">{{ money($payment->amount) }}</td>
                        <td class="text-right text-xs text-ink-500">{{ $payment->created_at->format('d M, h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-ink-500">No payments yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $payments->links() }}</div>
@endsection

@extends('layouts.admin')
@section('pageTitle', 'Refunds')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-xl font-bold">Refunds</h1>
        <a href="{{ route('admin.refunds.export', request()->query()) }}" class="btn-ghost btn-md">Export CSV</a>
    </div>

    <x-admin.filters
        :action="route('admin.refunds.index')"
        search-placeholder="Search booking reference…"
        :filters="[
            ['name' => 'status', 'label' => 'Status', 'options' => ['requested' => 'Requested', 'initiated' => 'Initiated', 'processed' => 'Processed', 'rejected' => 'Rejected', 'failed' => 'Failed']],
        ]"
        :count="$refunds->total()">
        <input type="date" name="from" value="{{ request('from') }}" class="input !w-auto" title="From date">
        <input type="date" name="to" value="{{ request('to') }}" class="input !w-auto" title="To date">
    </x-admin.filters>

    <div class="admin-card mt-4 overflow-x-auto">
        <table class="admin-table">
            <thead><tr>
                <th>Booking</th>
                <th>Customer</th>
                <x-admin.sort-header column="amount" label="Amount" />
                <th>Penalty</th>
                <x-admin.sort-header column="status" label="Status" />
                <th>Reason</th>
                <th class="text-right">Actions</th>
            </tr></thead>
            <tbody>
                @forelse ($refunds as $refund)
                    <tr>
                        <td><a href="{{ route('admin.bookings.show', $refund->booking) }}" class="font-bold text-brand-600">{{ $refund->booking->booking_reference }}</a></td>
                        <td>{{ $refund->booking->user?->name ?? 'Guest' }}</td>
                        <td class="font-bold">{{ money($refund->amount) }}</td>
                        <td>{{ $refund->penalty_amount ? money($refund->penalty_amount) : '—' }}</td>
                        <td><span class="status-pill {{ status_pill_class($refund->status) }}">{{ ucfirst($refund->status) }}</span></td>
                        <td class="max-w-[220px] truncate text-xs text-ink-500">{{ $refund->reason }}</td>
                        <td class="text-right">
                            @if ($refund->status === 'initiated')
                                <form action="{{ route('admin.refunds.process', $refund) }}" method="POST" class="inline">
                                    @csrf
                                    <button class="btn-primary btn-sm">Process</button>
                                </form>
                                <form action="{{ route('admin.refunds.reject', $refund) }}" method="POST" class="inline">
                                    @csrf
                                    <button class="ml-2 font-bold text-rose-500 hover:text-rose-700">Reject</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-ink-500">No refunds</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $refunds->links() }}</div>
@endsection

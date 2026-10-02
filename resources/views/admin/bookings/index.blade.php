@extends('layouts.admin')
@section('pageTitle', 'Bookings')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-xl font-bold">Bookings</h1>
        <div class="flex flex-wrap items-center gap-2">
            @if (auth('admin')->user()?->can('edit_booking') || auth('admin')->user()?->is_super_admin)
                <a href="{{ route('admin.bookings.create') }}" class="btn-primary btn-md">+ Add Booking</a>
            @endif
            <a href="{{ route('admin.bookings.export', request()->only(['type', 'status', 'q', 'gateway', 'from', 'to', 'sort', 'per_page'])) }}" class="btn-ghost btn-md">Export CSV</a>
        </div>
    </div>

    <div class="mt-3 flex flex-wrap gap-2">
        @foreach (['' => 'All', 'flight' => 'Flights', 'hotel' => 'Hotels', 'cab' => 'Cabs', 'package' => 'Packages'] as $key => $label)
            <a href="{{ route('admin.bookings.index', array_filter(['type' => $key, 'status' => request('status')])) }}"
               class="rounded-full px-4 py-1.5 text-xs font-semibold {{ (request('type') ?? '') === $key ? 'bg-brand-600 text-white' : 'bg-white text-ink-700 ring-1 ring-slate-200' }}">{{ $label }}</a>
        @endforeach
        @foreach (['confirmed' => 'Confirmed', 'pending' => 'Pending', 'payment_pending' => 'Payment Pending', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded'] as $key => $label)
            <a href="{{ route('admin.bookings.index', array_filter(['type' => request('type'), 'status' => $key])) }}"
               class="rounded-full px-4 py-1.5 text-xs font-semibold {{ request('status') === $key ? 'bg-ink-900 text-white' : 'bg-white text-ink-700 ring-1 ring-slate-200' }}">{{ $label }}</a>
        @endforeach
    </div>

    <x-admin.filters
        :action="route('admin.bookings.index')"
        search-placeholder="Search reference, customer, email…"
        :filters="[
            ['name' => 'gateway', 'label' => 'Gateway', 'options' => $gateways],
        ]"
        :sorts="['newest' => 'Newest first', 'oldest' => 'Oldest first', 'amount_high' => 'Amount high→low', 'amount_low' => 'Amount low→high']"
        :count="$bookings->total()">
        {{-- preserve the active pill selections when the bar auto-submits --}}
        @if (request('type')) <input type="hidden" name="type" value="{{ request('type') }}"> @endif
        @if (request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
        <input type="date" name="from" value="{{ request('from') }}" class="input !w-auto" title="From date">
        <input type="date" name="to" value="{{ request('to') }}" class="input !w-auto" title="To date">
    </x-admin.filters>

    <div class="admin-card mt-4 overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <x-admin.sort-header column="booking_reference" label="Reference" />
                    <th>Product</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <x-admin.sort-header column="total_amount" label="Amount" align="right" />
                    <x-admin.sort-header column="created_at" label="Date" align="right" />
                </tr>
            </thead>
            <tbody>
                @forelse ($bookings as $booking)
                    <tr>
                        <td><a href="{{ route('admin.bookings.show', $booking) }}" class="font-bold text-brand-600 hover:text-brand-800">{{ $booking->booking_reference }}</a></td>
                        <td class="capitalize">{{ $booking->product_type }}</td>
                        <td>{{ $booking->user?->name ?? ($booking->contact['first_name'] ?? 'Guest') }}</td>
                        <td>
                            @if (auth('admin')->user()?->can('edit_booking') || auth('admin')->user()?->is_super_admin)
                                <form action="{{ route('admin.bookings.status', $booking) }}" method="POST" class="inline-block">
                                    @csrf
                                    <div class="relative inline-flex items-center">
                                        <select name="status" class="status-pill {{ status_pill_class($booking->status) }} appearance-none pr-5 text-xs font-semibold cursor-pointer border-0 ring-1 ring-black/10 focus:ring-2 focus:ring-brand-500" onchange="this.form.submit()" title="Click to change status immediately">
                                            @foreach (\App\Models\Booking::STATUSES as $statusOption)
                                                <option value="{{ $statusOption }}" @selected($booking->status === $statusOption)>{{ label_case($statusOption) }}</option>
                                            @endforeach
                                        </select>
                                        <span class="pointer-events-none absolute right-1.5 text-[9px] opacity-70">▾</span>
                                    </div>
                                </form>
                            @else
                                <span class="status-pill {{ status_pill_class($booking->status) }}">{{ label_case($booking->status) }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($booking->payments->where('status', 'captured')->count())
                                <span class="status-pill bg-emerald-100 text-emerald-700">Paid</span>
                            @else
                                <span class="status-pill bg-slate-100 text-slate-600">Unpaid</span>
                            @endif
                        </td>
                        <td class="text-right font-bold">{{ money($booking->total_amount) }}</td>
                        <td class="text-right text-xs text-ink-500">{{ $booking->created_at->format('d M Y, h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-ink-500">No bookings found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $bookings->links() }}</div>
@endsection

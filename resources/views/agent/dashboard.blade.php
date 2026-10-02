@extends('layouts.site')

@section('page')
<x-agent.shell agentNav="dashboard">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold">Welcome, {{ $agent->contact_person ?: $agent->agency_name }}</h1>
        <p class="mt-1 text-sm text-ink-500">Here's an overview of your agent account.</p>
    </div>

    {{-- Financial cards --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Wallet Balance</p>
            <p class="mt-2 text-2xl font-bold text-ink-900">{{ money($stats['wallet_balance'], true) }}</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Available Credit</p>
            <p class="mt-2 text-2xl font-bold text-ink-900">{{ money($stats['available_credit'], true) }}</p>
            <p class="mt-1 text-xs text-ink-400">Limit {{ money($agent->credit_limit, true) }}</p>
        </div>
        <div class="card p-5 bg-brand-600 text-white">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-100">Purchasing Power</p>
            <p class="mt-2 text-2xl font-bold">{{ money($stats['purchasing_power'], true) }}</p>
        </div>
    </div>

    {{-- Booking stats --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Total Bookings</p>
            <p class="mt-2 text-2xl font-bold text-ink-900">{{ $stats['total_bookings'] }}</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Confirmed</p>
            <p class="mt-2 text-2xl font-bold text-ink-900">{{ $stats['confirmed_bookings'] }}</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Total Commission</p>
            <p class="mt-2 text-2xl font-bold text-emerald-600">{{ money($stats['total_commission'], true) }}</p>
        </div>
    </div>

    @if ($agent->status !== 'approved')
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Your account status is <strong>{{ label_case($agent->status) }}</strong>.
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        {{-- Recent bookings --}}
        <div class="card p-5">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-display text-lg font-bold">Recent Bookings</h2>
                <a href="{{ route('agent.bookings') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-800">View all</a>
            </div>
            @forelse ($recentBookings as $booking)
                <div class="flex items-center justify-between border-b border-ink-100 py-2.5 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink-900">{{ $booking->booking_reference }}</p>
                        <p class="text-xs text-ink-500">{{ label_case($booking->product_type) }} · {{ $booking->created_at->format('d M Y') }}</p>
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ status_pill_class($booking->status) }}">{{ label_case($booking->status) }}</span>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-ink-400">No bookings yet.</p>
            @endforelse
        </div>

        {{-- Recent transactions --}}
        <div class="card p-5">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-display text-lg font-bold">Recent Transactions</h2>
                <a href="{{ route('agent.wallet') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-800">View ledger</a>
            </div>
            @forelse ($recentTransactions as $txn)
                <div class="flex items-center justify-between border-b border-ink-100 py-2.5 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink-900">{{ label_case($txn->type) }}</p>
                        <p class="text-xs text-ink-500">{{ $txn->created_at->format('d M Y, H:i') }}</p>
                    </div>
                    <span class="text-sm font-bold {{ in_array($txn->type, ['deposit', 'booking_credit', 'refund', 'commission']) ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ in_array($txn->type, ['deposit', 'booking_credit', 'refund', 'commission']) ? '+' : '−' }}{{ money($txn->amount, true) }}
                    </span>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-ink-400">No transactions yet.</p>
            @endforelse
        </div>
    </div>
</x-agent.shell>
@endsection

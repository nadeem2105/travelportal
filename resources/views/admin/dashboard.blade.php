@extends('layouts.admin')
@section('pageTitle', 'Dashboard')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Total Bookings', $stats['total_bookings'], 'from-brand-500 to-brand-700'],
            ["Today's Bookings", $stats['bookings_today'], 'from-teal-500 to-teal-700'],
            ['Revenue (All Time)', money($stats['revenue']), 'from-amber-500 to-orange-600'],
            ['Revenue (This Month)', money($stats['revenue_month']), 'from-violet-500 to-violet-700'],
        ] as [$label, $value, $gradient])
            <div class="rounded-2xl bg-gradient-to-br {{ $gradient }} p-5 text-white shadow-card">
                <p class="text-xs font-semibold uppercase tracking-wider text-white/80">{{ $label }}</p>
                <p class="font-display mt-1 text-3xl font-extrabold">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Pending Bookings', $stats['pending'], 'text-amber-600'],
            ['Confirmed', $stats['confirmed'], 'text-emerald-600'],
            ['Cancelled', $stats['cancelled'], 'text-rose-600'],
            ['Refunds Pending', $stats['refunds_pending'], 'text-violet-600'],
        ] as [$label, $value, $color])
            <div class="admin-card">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-500">{{ $label }}</p>
                <p class="font-display mt-1 text-2xl font-extrabold {{ $color }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    @if ($stats['reconciliation'] > 0)
        <a href="{{ route('admin.reconciliation') }}" class="alert-warn mt-4 flex items-center justify-between">
            <span>⚠️ {{ $stats['reconciliation'] }} booking(s) need reconciliation — payment received but supplier booking failed.</span>
            <span class="font-bold">Review →</span>
        </a>
    @endif

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        {{-- Revenue chart --}}
        <div class="admin-card lg:col-span-2">
            <h2 class="font-display text-base font-bold">Revenue — Last 12 Months</h2>
            <div class="mt-4" style="height:280px">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        {{-- Product-wise sales --}}
        <div class="admin-card">
            <h2 class="font-display text-base font-bold">Bookings by Product</h2>
            <div class="mt-4" style="height:280px">
                <canvas id="productChart"></canvas>
            </div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        {{-- Recent bookings --}}
        <div class="admin-card overflow-x-auto">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-base font-bold">Recent Bookings</h2>
                <a href="{{ route('admin.bookings.index') }}" class="text-sm font-bold text-brand-600 hover:text-brand-800">View All →</a>
            </div>
            <table class="admin-table mt-3">
                <thead><tr><th>Reference</th><th>Customer</th><th>Status</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                    @forelse ($recentBookings as $booking)
                        <tr>
                            <td><a href="{{ route('admin.bookings.show', $booking) }}" class="font-bold text-brand-600 hover:text-brand-800">{{ $booking->booking_reference }}</a></td>
                            <td>{{ $booking->user?->name ?? ($booking->contact['first_name'] ?? 'Guest') }}</td>
                            <td><span class="status-pill {{ status_pill_class($booking->status) }}">{{ label_case($booking->status) }}</span></td>
                            <td class="text-right font-bold">{{ money($booking->total_amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-ink-500">No bookings yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Recent tickets + counts --}}
        <div class="admin-card">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-base font-bold">Support & Other</h2>
                <div class="flex gap-3 text-xs font-semibold">
                    <span class="rounded-full bg-brand-50 px-3 py-1 text-brand-700">{{ $stats['customers'] }} customers</span>
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-amber-700">{{ $stats['open_tickets'] }} open tickets</span>
                    <span class="rounded-full bg-rose-50 px-3 py-1 text-rose-700">{{ $stats['unread_messages'] }} messages</span>
                </div>
            </div>
            <table class="admin-table mt-3">
                <thead><tr><th>Ticket</th><th>Subject</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($recentTickets as $ticket)
                        <tr>
                            <td><a href="{{ route('admin.tickets.show', $ticket) }}" class="font-bold text-brand-600">{{ $ticket->ticket_no }}</a></td>
                            <td class="max-w-[200px] truncate">{{ $ticket->subject }}</td>
                            <td><span class="status-pill {{ status_pill_class($ticket->status) }}">{{ label_case($ticket->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-ink-500">No tickets</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: @json($monthly->pluck('month')),
                    datasets: [{
                        label: 'Revenue (₹)',
                        data: @json($monthly->pluck('revenue')),
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.08)',
                        fill: true,
                        tension: 0.35,
                    }],
                },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } } },
            });
        }

        const productCtx = document.getElementById('productChart');
        if (productCtx) {
            new Chart(productCtx, {
                type: 'doughnut',
                data: {
                    labels: @json(array_map('ucfirst', array_keys($productWise))),
                    datasets: [{
                        data: @json(array_values($productWise)),
                        backgroundColor: ['#2563eb', '#0d9488', '#f59e0b', '#8b5cf6'],
                    }],
                },
                options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
            });
        }
    });
</script>
@endpush

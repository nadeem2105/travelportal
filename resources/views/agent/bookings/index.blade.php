@extends('layouts.site')

@section('page')
<x-agent.shell agentNav="bookings">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold">My Bookings</h1>
            <p class="mt-1 text-sm text-ink-500">All bookings placed through your agent account.</p>
        </div>
        <form method="GET">
            <select name="status" class="input" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach (['confirmed', 'payment_pending', 'cancelled', 'completed'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ label_case($s) }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-ink-50 text-left text-xs font-semibold uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3">Reference</th>
                        <th class="px-5 py-3">Type</th>
                        <th class="px-5 py-3">Customer</th>
                        <th class="px-5 py-3">Date</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                        <th class="px-5 py-3 text-right">Commission</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($bookings as $booking)
                        <tr>
                            <td class="px-5 py-3 font-semibold text-ink-900">{{ $booking->booking_reference }}</td>
                            <td class="px-5 py-3 text-ink-600">{{ label_case($booking->product_type) }}</td>
                            <td class="px-5 py-3 text-ink-600">{{ $booking->contact['full_name'] ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-ink-500">{{ $booking->created_at->format('d M Y') }}</td>
                            <td class="px-5 py-3 text-right text-ink-700">{{ money($booking->total_amount, true) }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-emerald-600">{{ money($booking->agent_commission, true) }}</td>
                            <td class="px-5 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ status_pill_class($booking->status) }}">{{ label_case($booking->status) }}</span></td>
                            <td class="px-5 py-3 text-right"><a href="{{ route('agent.bookings.show', $booking) }}" class="text-sm font-semibold text-brand-600 hover:text-brand-800">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-12 text-center text-ink-400">No bookings yet. <a href="{{ route('agent.packages') }}" class="font-semibold text-brand-600">Book a package →</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($bookings->hasPages())
            <div class="border-t border-ink-100 p-4">{{ $bookings->links() }}</div>
        @endif
    </div>
</x-agent.shell>
@endsection

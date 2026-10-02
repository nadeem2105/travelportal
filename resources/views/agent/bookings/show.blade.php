@extends('layouts.site')

@section('page')
<x-agent.shell agentNav="bookings">
    <div class="mb-6">
        <a href="{{ route('agent.bookings') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-800">&larr; Back to bookings</a>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-2xl font-bold">Booking {{ $booking->booking_reference }}</h1>
            <span class="rounded-full px-3 py-1 text-sm font-semibold {{ status_pill_class($booking->status) }}">{{ label_case($booking->status) }}</span>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="space-y-6">
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-bold">Details</h2>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-ink-400">Product</dt><dd class="font-semibold text-ink-900">{{ label_case($booking->product_type) }}</dd></div>
                    <div><dt class="text-ink-400">Booked on</dt><dd class="font-semibold text-ink-900">{{ $booking->created_at->format('d M Y, H:i') }}</dd></div>
                    @if ($booking->packageBooking)
                        <div><dt class="text-ink-400">Package</dt><dd class="font-semibold text-ink-900">{{ $booking->packageBooking->package_name }}</dd></div>
                        <div><dt class="text-ink-400">Departure</dt><dd class="font-semibold text-ink-900">{{ $booking->packageBooking->departure_date ? \Illuminate\Support\Carbon::parse($booking->packageBooking->departure_date)->format('d M Y') : '—' }}</dd></div>
                        <div><dt class="text-ink-400">Travellers</dt><dd class="font-semibold text-ink-900">{{ $booking->packageBooking->adults }} adults, {{ $booking->packageBooking->children }} children</dd></div>
                    @endif
                </dl>
            </div>

            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-bold">Customer</h2>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-ink-400">Name</dt><dd class="font-semibold text-ink-900">{{ $booking->contact['full_name'] ?? '—' }}</dd></div>
                    <div><dt class="text-ink-400">Phone</dt><dd class="font-semibold text-ink-900">{{ $booking->contact['phone'] ?? '—' }}</dd></div>
                    <div><dt class="text-ink-400">Email</dt><dd class="font-semibold text-ink-900">{{ $booking->contact['email'] ?? '—' }}</dd></div>
                </dl>
            </div>

            @if ($booking->items->count())
                <div class="card p-6">
                    <h2 class="mb-4 font-display text-lg font-bold">Line items</h2>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-ink-100">
                            @foreach ($booking->items as $item)
                                @continue($item->quantity <= 0)
                                <tr>
                                    <td class="py-2 text-ink-700">{{ $item->name }} <span class="text-ink-400">× {{ $item->quantity }}</span></td>
                                    <td class="py-2 text-right font-semibold text-ink-900">{{ money($item->total_price, true) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="h-fit lg:sticky lg:top-24">
            <div class="card p-6">
                <h2 class="mb-4 font-display text-lg font-bold">Payment</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between text-ink-600"><dt>Public total</dt><dd>{{ money($booking->price_breakdown['public_total'] ?? $booking->total_amount, true) }}</dd></div>
                    <div class="flex justify-between text-emerald-600"><dt>Your commission</dt><dd>{{ money($booking->agent_commission, true) }}</dd></div>
                    <div class="flex justify-between border-t border-ink-100 pt-2 text-base font-bold text-ink-900"><dt>Net paid</dt><dd>{{ money(($booking->price_breakdown['agent_net_payable'] ?? ($booking->total_amount - $booking->agent_commission)), true) }}</dd></div>
                </dl>
            </div>
        </div>
    </div>
</x-agent.shell>
@endsection

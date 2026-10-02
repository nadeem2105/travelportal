@extends('layouts.site')

@section('page')
<section class="shell max-w-5xl py-10">
    <h1 class="font-display text-2xl font-bold">Secure Checkout</h1>
    <p class="text-sm text-ink-500">Booking reference: <strong class="text-ink-900">{{ $booking->booking_reference }}</strong></p>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]">
        <div class="space-y-5">
            {{-- Booking details --}}
            <div class="card p-6">
                <h2 class="font-display text-lg font-bold capitalize">{{ $booking->product_type }} Booking</h2>
                @foreach ($booking->items as $item)
                    @php
                        $d = $item->details ?? [];
                        $qtyLabel = null;
                        if (($item->item_type ?? null) === 'hotel_room' && !empty($d['nights'])) {
                            $nights = (int) $d['nights'];
                            $roomsCount = (int) ($d['rooms'] ?? 1);
                            $qtyLabel = $roomsCount.' '.\Illuminate\Support\Str::plural('room', $roomsCount)
                                .' × '.$nights.' '.\Illuminate\Support\Str::plural('night', $nights);
                        } elseif ($item->quantity > 1) {
                            $qtyLabel = '× '.$item->quantity;
                        }
                    @endphp
                    <div class="mt-3 flex items-center justify-between text-sm">
                        <span class="text-ink-700">{{ $item->name }} @if($qtyLabel) <span class="text-ink-500">({{ $qtyLabel }})</span> @endif</span>
                        <span class="font-semibold">{{ money($item->total_price) }}</span>
                    </div>
                @endforeach
                @if ($booking->travellers->count())
                    <div class="divider my-4"></div>
                    <p class="text-xs font-bold uppercase tracking-wider text-ink-500">Lead Traveller</p>
                    @foreach ($booking->travellers->take(3) as $traveller)
                        <p class="mt-1 text-sm">{{ $traveller->full_name }}</p>
                    @endforeach
                @endif
            </div>

            {{-- Coupon --}}
            <div class="card p-6">
                <h2 class="font-display text-lg font-bold">Have a Coupon?</h2>
                <form action="{{ route('checkout.coupon', $booking) }}" method="POST" class="mt-3 flex gap-2">
                    @csrf
                    <input type="text" name="code" class="input uppercase" placeholder="e.g. KASHMIR10" value="{{ old('code') }}" required>
                    <button class="btn-ghost btn-md shrink-0">Apply</button>
                </form>
            </div>

            {{-- Payment methods --}}
            <div class="card p-6">
                <h2 class="font-display text-lg font-bold">Payment Method</h2>
                @if ($gateway)
                    <div class="mt-3 flex items-center gap-3 rounded-2xl border-2 border-brand-500 bg-brand-50/50 p-4">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-bold text-ink-900">{{ $gateway->name }}</p>
                            <p class="text-xs text-ink-500">UPI · Cards · NetBanking · Wallets — {{ strtoupper($gateway->mode) }} mode</p>
                        </div>
                    </div>
                    <form action="{{ route('checkout.pay', $booking) }}" method="POST" class="mt-4"
                          x-data="{ submitting: false, agree: false }" @submit="submitting = true">
                        @csrf
                        <label class="mb-3 flex items-start gap-2 text-xs text-ink-600">
                            <input type="checkbox" name="accept_terms" value="1" x-model="agree" required class="mt-0.5 rounded border-slate-300">
                            <span>
                                I have read and accept the
                                <a href="{{ route('page.show', 'terms-and-conditions') }}" target="_blank" class="text-brand-700 underline">Terms &amp; Conditions</a>,
                                <a href="{{ route('page.show', 'cancellation-policy') }}" target="_blank" class="text-brand-700 underline">Cancellation Policy</a>
                                and <a href="{{ route('page.show', 'privacy-policy') }}" target="_blank" class="text-brand-700 underline">Privacy Policy</a>.
                            </span>
                        </label>
                        <button class="btn-primary btn-lg w-full" :disabled="submitting || !agree" :class="(submitting || !agree) ? 'opacity-60 cursor-not-allowed' : ''">
                            <span x-show="!submitting">Pay {{ money($booking->total_amount) }} Securely</span>
                            <span x-show="submitting" x-cloak>Processing…</span>
                            <svg class="h-5 w-5" x-show="!submitting" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                        </button>
                    </form>
                @else
                    <div class="alert-warn mt-3">No payment gateway is currently enabled. Please contact support at {{ settings('company_phone') }}.</div>
                @endif
                <p class="mt-3 text-center text-[11px] text-ink-500">🔒 256-bit encrypted · We never store your card details</p>
            </div>
        </div>

        {{-- Price summary --}}
        <aside class="h-fit lg:sticky lg:top-24">
            <div class="card p-6">
                <h2 class="font-display text-lg font-bold">Price Summary</h2>
                <div class="mt-4 space-y-2.5 text-sm">
                    <div class="flex justify-between"><span class="text-ink-500">Subtotal</span><span>{{ money($booking->subtotal) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-500">Taxes &amp; Fees</span><span>{{ money($booking->tax_amount) }}</span></div>
                    @if ($booking->discount_amount > 0)
                        <div class="flex justify-between text-emerald-600"><span>Coupon Discount</span><span>-{{ money($booking->discount_amount) }}</span></div>
                    @endif
                    <div class="divider"></div>
                    <div class="flex justify-between text-base font-extrabold"><span>Total Payable</span><span>{{ money($booking->total_amount) }}</span></div>
                </div>
                @if ($booking->expires_at)
                    <p class="mt-4 text-center text-xs text-rose-500">⏱ Complete payment before {{ $booking->expires_at->format('h:i A') }} to lock this fare.</p>
                @endif
            </div>
        </aside>
    </div>
</section>

<x-track-event event="begin_checkout" fb="InitiateCheckout" :data="[
    'currency' => 'INR',
    'value' => (float) $booking->total_amount,
    'booking_reference' => $booking->booking_reference,
    'content_type' => $booking->product_type,
]" />
@endsection

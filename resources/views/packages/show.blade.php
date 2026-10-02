@extends('layouts.site')
@section('activeNav', 'packages')

@section('page')
<section class="shell max-w-5xl pt-28">
    <nav class="text-xs text-ink-500">
        <a href="{{ route('home') }}" class="hover:text-brand-700">Home</a> ›
        <a href="{{ route('packages.index') }}" class="hover:text-brand-700">Packages</a> ›
        <span class="text-ink-900">{{ $package->name }}</span>
    </nav>

    <div class="mt-4 overflow-hidden rounded-2xl">
        <img src="{{ asset(img($package->cover_image, 'images/packages/' . $package->slug . '.svg')) }}" class="h-80 w-full object-cover" alt="{{ $package->name }}">
    </div>

    <div class="mt-6 grid gap-8 lg:grid-cols-[1fr_340px]">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-display text-3xl font-bold">{{ $package->name }}</h1>
                <span class="badge-soft">{{ $package->duration_days }}D / {{ $package->duration_nights }}N</span>
            </div>
            @if ($package->destination)
                <p class="mt-1 text-sm text-ink-500">📍 {{ $package->destination->name }} · {{ $package->package_type }} tour</p>
            @endif

            <div class="prose-page mt-5">{!! nl2br(e($package->description ?? $package->short_description)) !!}</div>

            @if ($package->highlights)
                <h2 class="mt-8 font-display text-xl font-bold">Tour Highlights</h2>
                <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($package->highlights as $highlight)
                        <li class="flex gap-2 text-sm text-ink-700"><span class="text-emerald-500">✓</span> {{ $highlight }}</li>
                    @endforeach
                </ul>
            @endif

            {{-- Itinerary --}}
            @if ($package->itineraries->count())
                <h2 class="mt-8 font-display text-xl font-bold">Day-wise Itinerary</h2>
                <div class="mt-4 space-y-3">
                    @foreach ($package->itineraries as $day)
                        <details class="card overflow-hidden p-0" @if($loop->first) open @endif>
                            <summary class="cursor-pointer list-none p-4 font-semibold text-ink-900 transition hover:bg-brand-50/50">
                                <span class="mr-2 rounded-lg bg-brand-600 px-2 py-0.5 text-xs font-bold text-white">Day {{ $day->day_number }}</span>
                                {{ $day->title }}
                            </summary>
                            <div class="border-t border-slate-100 p-4 text-sm text-ink-700">
                                {!! nl2br(e($day->description)) !!}
                                @if ($day->meals)
                                    <p class="mt-2 text-xs text-ink-500">🍽 Meals: {{ implode(', ', $day->meals) }}</p>
                                @endif
                                @if ($day->overnight_stay)
                                    <p class="mt-1 text-xs text-ink-500">🏨 Overnight: {{ $day->overnight_stay }}</p>
                                @endif
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif

            @if ($package->hotels->count())
                <h2 class="mt-8 font-display text-xl font-bold">Hotels in this Package</h2>
                <div class="mt-3 space-y-2">
                    @foreach ($package->hotels as $hotelStay)
                        <div class="card flex items-center justify-between p-4 text-sm">
                            <span><strong>{{ $hotelStay->hotel_name }}</strong> @if($hotelStay->city) · {{ $hotelStay->city }} @endif</span>
                            <span class="text-ink-500">{{ $hotelStay->nights }} night(s) · {{ $hotelStay->category }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-8 grid gap-4 sm:grid-cols-2">
                <div class="card p-5">
                    <h3 class="font-display text-base font-bold text-emerald-600">✓ What's Included</h3>
                    <ul class="mt-3 space-y-1.5 text-sm text-ink-700">
                        @foreach ($package->inclusions ?? [] as $inclusion)
                            <li class="flex gap-2"><span class="text-emerald-500">✓</span> {{ $inclusion }}</li>
                        @endforeach
                    </ul>
                </div>
                <div class="card p-5">
                    <h3 class="font-display text-base font-bold text-rose-500">✕ Not Included</h3>
                    <ul class="mt-3 space-y-1.5 text-sm text-ink-700">
                        @foreach ($package->exclusions ?? [] as $exclusion)
                            <li class="flex gap-2"><span class="text-rose-400">✕</span> {{ $exclusion }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            @if ($reviews->count())
                <h2 class="mt-8 font-display text-xl font-bold">Traveller Reviews</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ($reviews as $review)
                        <div class="card p-4">
                            <x-rating :rating="$review->rating" :size="3" />
                            <p class="mt-2 text-sm text-ink-700">&ldquo;{{ $review->content }}&rdquo;</p>
                            <p class="mt-2 text-xs font-bold text-ink-900">{{ $review->user?->name ?? 'Traveller' }}
                                @if ($review->is_verified_booking) <span class="badge-soft ml-1 !text-[10px]">✓ Verified</span> @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Sticky booking card --}}
        <aside id="book" class="h-fit scroll-mt-24 lg:sticky lg:top-24">
            <div class="card p-6">
                @php
                    $activeDepartures = $package->departures->filter(fn($d) => $d->seatsLeft() > 0);
                    $lowestSeats = $activeDepartures->min(fn($d) => $d->seatsLeft());
                @endphp

                @if ($lowestSeats !== null && $lowestSeats <= 6)
                    <div class="mb-4 flex items-center gap-2 rounded-xl bg-gradient-to-r from-rose-500/10 to-amber-500/10 border border-rose-200 p-3 text-xs font-bold text-rose-700">
                        <span class="flex h-2 w-2 rounded-full bg-rose-500 animate-ping"></span>
                        🔥 High Demand: Only {{ $lowestSeats }} seats left on upcoming departure!
                    </div>
                @endif

                <div class="flex items-end gap-2">
                    <p class="font-display text-3xl font-extrabold text-ink-900">{{ money($price) }}</p>
                    <p class="pb-1 text-xs text-ink-500">/ person</p>
                    @if ($package->discount_percent > 0)
                        <span class="mb-1 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-600">{{ (int) $package->discount_percent }}% off</span>
                    @endif
                </div>
                <p class="mt-1 flex items-center gap-1 text-xs text-emerald-600 font-medium">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Popular choice: Instant confirmation &amp; flexible itinerary
                </p>

                {{-- Choose date + party size, then proceed to the multi-step booking wizard --}}
                <form action="{{ route('packages.booking-form', $package) }}" method="GET" class="mt-5 space-y-4"
                      x-data="{
                        mode: '{{ $package->departures->count() ? 'scheduled' : 'custom' }}',
                        get isCustom() { return this.mode === 'custom' },
                      }">
                    <div>
                        <label class="label">Departure Date</label>

                        @if ($package->departures->count())
                            {{-- Dropdown drives the picker: selecting "custom" reveals the date input --}}
                            <select x-model="mode" :name="isCustom ? null : 'departure_date'" class="input" :required="!isCustom">
                                @foreach ($package->departures as $departure)
                                    <option value="{{ $departure->departure_date->format('Y-m-d') }}" @disabled($departure->seatsLeft() < 1)>
                                        {{ $departure->departure_date->format('D, d M Y') }} — {{ $departure->seatsLeft() }} seats left {{ $departure->seatsLeft() <= 4 ? '🔥 Limited!' : '' }}
                                    </option>
                                @endforeach
                                <option value="custom">Custom date — pick your own</option>
                            </select>

                            {{-- Date picker appears only for the custom option --}}
                            <div x-cloak x-show="isCustom" x-transition.opacity.duration.200ms class="mt-3">
                                <input type="date" :name="isCustom ? 'departure_date' : null" class="input"
                                       min="{{ now()->addDays(2)->toDateString() }}" :required="isCustom">
                                <p class="mt-1 text-xs text-ink-500">Pick any date — we'll tailor the itinerary to your schedule.</p>
                            </div>
                        @else
                            {{-- No scheduled departures: date picker only --}}
                            <input type="date" name="departure_date" class="input" min="{{ now()->addDays(2)->toDateString() }}" required>
                            <p class="mt-1 text-xs text-ink-500">Flexible travel — pick any date and we'll tailor the itinerary.</p>
                        @endif
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="label">Adults</label>
                            <input type="number" name="adults" class="input" min="1" max="{{ $package->max_travellers }}" value="2" required>
                        </div>
                        <div>
                            <label class="label">Children</label>
                            <input type="number" name="children" class="input" min="0" max="10" value="0">
                        </div>
                        <div>
                            <label class="label">Rooms</label>
                            <input type="number" name="rooms" class="input" min="1" max="6" value="1">
                        </div>
                    </div>
                    <button class="btn-primary btn-lg w-full">
                        Book This Package
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </button>
                    <button type="button" x-data
                            @click="$dispatch('open-lead-modal', { source: 'package_detail', product_type: 'package', destination: @js($package->name ?? ''), title: 'Customise This Trip' })"
                            class="btn-ghost btn-md w-full">
                        Customise / Get Free Quote
                    </button>
                    <p class="text-center text-[11px] text-ink-500">Next: add traveller details &amp; billing · Free cancellation up to 7 days before departure</p>
                </form>
            </div>
        </aside>
    </div>

    @if ($similar->count())
        <h2 class="mt-12 font-display text-xl font-bold">You May Also Like</h2>
        <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($similar as $pkg)
                <x-package-card :package="$pkg" />
            @endforeach
        </div>
    @endif
</section>

{{-- Mobile sticky booking bar (M-1): persistent Book CTA on small screens --}}
<div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-4 py-3 shadow-[0_-6px_24px_rgba(15,23,42,0.08)] backdrop-blur lg:hidden">
    <div class="flex items-center gap-3">
        <div class="leading-tight">
            <p class="text-[11px] text-ink-500">Starting from</p>
            <p class="font-display text-lg font-extrabold text-ink-900">{{ money($price) }} <span class="text-[11px] font-medium text-ink-500">/ person</span></p>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <button type="button" x-data
                    @click="$dispatch('open-lead-modal', { source: 'package_sticky', product_type: 'package', destination: @js($package->name ?? ''), title: 'Get a Free Quote' })"
                    class="btn-ghost btn-md !px-3">Quote</button>
            <a href="#book" class="btn-primary btn-md !px-5">Book Now</a>
        </div>
    </div>
</div>

<x-track-event event="view_item" fb="ViewContent" :data="[
    'currency' => 'INR',
    'value' => (float) $price,
    'item_name' => $package->name ?? null,
    'item_id' => $package->id ?? null,
    'content_type' => 'package',
]" />
@endsection

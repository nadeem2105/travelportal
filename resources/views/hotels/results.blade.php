@extends('layouts.site')

@section('page')
<section class="shell py-10">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold">Hotels in {{ $params['destination'] }}</h1>
            <p class="mt-1 text-sm text-ink-500">
                {{ \Carbon\Carbon::parse($params['check_in'])->format('d M Y') }} → {{ \Carbon\Carbon::parse($params['check_out'])->format('d M Y') }}
                @php
                    $occ = [];
                    $occ[] = ($params['adults'] ?? 2).' '.\Illuminate\Support\Str::plural('adult', $params['adults'] ?? 2);
                    if (!empty($params['children'])) $occ[] = $params['children'].' '.\Illuminate\Support\Str::plural('child', $params['children']);
                    if (!empty($params['infants'])) $occ[] = $params['infants'].' '.\Illuminate\Support\Str::plural('infant', $params['infants']);
                @endphp
                · {{ implode(', ', $occ) }} · {{ $params['rooms'] }} {{ \Illuminate\Support\Str::plural('room', $params['rooms']) }}
            </p>
        </div>
        <a href="{{ route('hotels.index') }}" class="btn-ghost btn-md">Modify Search</a>
    </div>

    <div class="mt-6 space-y-4">
        @forelse ($hotels as $hotel)
            <article class="card card-hover flex flex-col overflow-hidden sm:flex-row">
                <a href="{{ route('hotels.show', $hotel['slug'] ?? '#') }}" class="block h-48 w-full shrink-0 overflow-hidden sm:w-72">
                    <img src="{{ asset(img($hotel['cover_image'], 'images/destinations/srinagar.svg')) }}" alt="{{ $hotel['name'] }}" class="h-full w-full object-cover transition duration-500 hover:scale-105">
                </a>
                <div class="flex flex-1 flex-col p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-display text-lg font-bold text-ink-900">{{ $hotel['name'] }}</h2>
                            <p class="text-xs text-amber-500">{{ str_repeat('★', $hotel['star_rating']) }}
                                <span class="ml-1 text-ink-500">{{ $hotel['address'] ?? $hotel['city'] }}</span></p>
                        </div>
                        @if ($hotel['user_rating'])
                            <span class="rounded-l-lg bg-brand-600 px-2.5 py-1.5 text-sm font-bold text-white">{{ $hotel['user_rating'] }}<span class="text-[10px] font-medium">/5</span></span>
                        @endif
                    </div>
                    <p class="mt-2 text-sm text-ink-500">{{ \Illuminate\Support\Str::limit($hotel['short_description'] ?? '', 140) }}</p>
                    <div class="amenity-list mt-3">
                        @foreach (array_slice($hotel['amenities'] ?? [], 0, 5) as $amenity)
                            <span class="chip">✓ {{ $amenity }}</span>
                        @endforeach
                    </div>
                    <div class="mt-auto flex flex-wrap items-end justify-between gap-3 pt-4">
                        <div>
                            @if (!empty($hotel['supplier_offers']) && count($hotel['supplier_offers']) > 1)
                                <p class="mb-1 text-[11px] font-medium text-emerald-600 flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Best deal across {{ count($hotel['supplier_offers']) }} suppliers
                                </p>
                            @endif
                            @if (($hotel['rooms_left'] ?? 10) < 4)
                                <p class="text-xs font-semibold text-rose-500">Only {{ $hotel['rooms_left'] }} rooms left!</p>
                            @endif
                            <p class="font-display text-xl font-extrabold text-ink-900">{{ money($hotel['display_price'] ?? $hotel['starting_price']) }}
                                <span class="text-xs font-medium text-ink-500">/ night</span></p>
                        </div>
                        <a href="{{ route('hotels.show', $hotel['slug']) }}" class="btn-primary btn-md">View Rooms</a>
                    </div>
                </div>
            </article>
        @empty
            <div class="card p-12 text-center">
                <p class="font-display text-lg font-bold">No hotels found for "{{ $params['destination'] }}"</p>
                <p class="mt-1 text-sm text-ink-500">Try a nearby destination or different dates.</p>
            </div>
        @endforelse
    </div>
</section>
@endsection

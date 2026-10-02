{{-- Handpicked hotels & stays showcase --}}
@php($hotels = $hotels ?? collect())
@if ($hotels->count())
    <section class="shell py-10">
        @include('home.sections._header', [
            'title' => $section->title ?? 'Handpicked Stays',
            'subtitle' => $section->subtitle ?? 'Luxury resorts, houseboats and cosy lodges',
            'ctaText' => $section->cta_text ?? 'View All Hotels',
            'ctaUrl' => $section->cta_url ?? route('hotels.index'),
            'cta' => $section->cta_text !== null,
        ])
        <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($hotels as $hotel)
                <a href="{{ route('hotels.show', $hotel) }}" class="card card-hover overflow-hidden">
                    <span class="block h-44 overflow-hidden">
                        <img src="{{ asset(img($hotel->cover_image, 'images/destinations/srinagar.svg')) }}" alt="{{ $hotel->name }}" class="h-full w-full object-cover transition duration-500 hover:scale-105" loading="lazy">
                    </span>
                    <span class="block p-4">
                        <span class="flex items-center justify-between gap-2">
                            <span class="font-display block truncate text-sm font-bold text-ink-900">{{ $hotel->name }}</span>
                            @if ($hotel->star_rating)<span class="shrink-0 text-xs text-amber-500">{{ str_repeat('★', (int) $hotel->star_rating) }}</span>@endif
                        </span>
                        <span class="mt-1 block text-xs text-ink-500">{{ $hotel->city ?? optional($hotel->destination)->name }}@foreach(array_slice($hotel->amenities ?? [], 0, 2) as $a) · {{ $a }}@endforeach</span>
                        @if ($hotel->starting_price)
                            <span class="mt-2 block text-sm font-bold text-brand-700">From {{ money($hotel->starting_price) }} <span class="text-xs font-medium text-ink-500">/ night</span></span>
                        @endif
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endif

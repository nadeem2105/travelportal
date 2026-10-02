{{-- Offers strip — MakeMyTrip-style horizontal cards --}}
@php($offers = $offers ?? collect())
@if ($offers->count())
    <section class="shell py-10">
        @include('home.sections._header', [
            'title' => $section->title ?? 'Offers & Coupons',
            'subtitle' => $section->subtitle ?? 'Save more on your next Kashmir trip',
            'ctaText' => $section->cta_text ?? 'All Offers',
            'ctaUrl' => route('offers.index'),
        ])

        <div class="scroll-row mt-6 snap-x">
            @foreach ($offers as $offer)
                <article class="offer-card group">
                    {{-- Thumbnail --}}
                    <a href="{{ $offer->link_url ?? route('offers.index') }}" class="offer-card-media">
                        <img src="{{ asset(img($offer->image, 'images/destinations/srinagar.svg')) }}"
                             alt="{{ $offer->title }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                    </a>

                    {{-- Body --}}
                    <div class="flex min-w-0 flex-1 flex-col self-stretch">
                        <a href="{{ route('offers.index') }}" class="self-end text-[11px] font-semibold uppercase tracking-wide text-ink-400 hover:text-ink-600">T&amp;C's Apply</a>

                        <h3 class="mt-1 line-clamp-2 font-display text-lg font-extrabold leading-tight text-ink-900">{{ $offer->title }}</h3>
                        <span class="mt-2 block h-0.5 w-10 rounded-full bg-brand-600"></span>

                        @if ($offer->description)
                            <p class="mt-2 line-clamp-1 text-sm text-ink-500">{{ $offer->description }}</p>
                        @elseif ($offer->discount_text)
                            <p class="mt-2 text-sm font-semibold text-emerald-600">{{ $offer->discount_text }}</p>
                        @endif

                        {{-- Footer: Code + Book Now --}}
                        <div class="mt-auto flex items-center justify-between pt-4">
                            @if ($offer->coupon?->code)
                                <span class="text-sm text-ink-500">Code: <span class="font-mono font-bold text-ink-700">{{ $offer->coupon->code }}</span></span>
                            @else
                                <span></span>
                            @endif
                            <a href="{{ $offer->link_url ?? route('offers.index') }}" class="text-sm font-bold uppercase tracking-wide text-brand-600 hover:text-brand-800">
                                {{ $offer->button_text ?? 'Book Now' }}
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif

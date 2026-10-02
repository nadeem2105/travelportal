@extends('layouts.site')

@section('hero')
    <section class="hero">
        <div class="hero-media"><img src="{{ hero_banner('images/destinations/srinagar.svg') }}" alt="Hotels in Kashmir"></div>
        <div class="hero-scrim"></div>
        <div class="shell relative pb-8 pt-14">
            <h1 class="font-display text-4xl font-extrabold text-ink-900">Hotels &amp; <span class="text-brand-600">Houseboats</span></h1>
            <p class="mt-2 max-w-lg text-[15px] text-ink-700">From luxury resorts on Dal Lake to cosy Gulmarg ski lodges.</p>
            <div class="mt-8">
                <x-search-widget :initialTab="'hotels'" />
            </div>
        </div>
    </section>
@endsection

@section('page')
<section class="shell py-10">
    <h2 class="section-title">Featured Stays</h2>
    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($featuredHotels as $hotel)
            <a href="{{ route('hotels.show', $hotel) }}" class="card card-hover overflow-hidden">
                <span class="block h-44 overflow-hidden">
                    <img src="{{ asset(img($hotel->cover_image, 'images/destinations/srinagar.svg')) }}" alt="{{ $hotel->name }}" class="h-full w-full object-cover transition duration-500 hover:scale-105" loading="lazy">
                </span>
                <span class="block p-4">
                    <span class="flex items-center justify-between">
                        <span class="font-display block text-sm font-bold text-ink-900">{{ $hotel->name }}</span>
                        <span class="text-xs text-amber-500">{{ str_repeat('★', $hotel->star_rating) }}</span>
                    </span>
                    <span class="mt-1 block text-xs text-ink-500">{{ $hotel->city }} @foreach(array_slice($hotel->amenities ?? [], 0, 3) as $a) · {{ $a }} @endforeach</span>
                    <span class="mt-2 block text-sm font-bold text-brand-700">From {{ money($hotel->starting_price ?? $hotel->rooms->min('base_price')) }} <span class="text-xs font-medium text-ink-500">/ night</span></span>
                </span>
            </a>
        @endforeach
    </div>
</section>
@endsection

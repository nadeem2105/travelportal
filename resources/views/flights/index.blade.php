@extends('layouts.site')

@section('hero')
    <section class="hero">
        <div class="hero-media"><img src="{{ hero_banner('images/hero.svg') }}" alt="Flights to Kashmir"></div>
        <div class="hero-scrim"></div>
        <div class="shell relative pb-8 pt-14">
            <h1 class="font-display text-4xl font-extrabold text-ink-900">Book Flights to <span class="text-brand-600">Kashmir</span></h1>
            <p class="mt-2 max-w-lg text-[15px] text-ink-700">Compare fares across airlines and grab the best deals to Srinagar &amp; Jammu.</p>
            <div class="mt-8">
                <x-search-widget :initialTab="'flights'" />
            </div>
        </div>
    </section>
@endsection

@section('page')
<section class="shell py-10">
    <h2 class="section-title">Popular Flight Routes</h2>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($popularRoutes as $route)
            <a href="{{ route('flights.results', ['from' => $route['from'], 'to' => $route['to'], 'departure' => now()->addDays(7)->format('Y-m-d')]) }}" class="card card-hover flex items-center justify-between p-5">
                <span class="font-semibold text-ink-900">{{ $route['label'] }}</span>
                <svg class="h-4 w-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5"/></svg>
            </a>
        @endforeach
    </div>
</section>
@endsection

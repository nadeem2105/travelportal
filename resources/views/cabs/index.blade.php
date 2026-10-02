@extends('layouts.site')

@section('hero')
    <section class="hero">
        <div class="hero-media"><img src="{{ hero_banner('images/destinations/gulmarg.svg') }}" alt="Cabs in Kashmir"></div>
        <div class="hero-scrim"></div>
        <div class="shell relative pb-8 pt-14">
            <h1 class="font-display text-4xl font-extrabold text-ink-900">Cabs &amp; <span class="text-brand-600">Transfers</span></h1>
            <p class="mt-2 max-w-lg text-[15px] text-ink-700">Airport transfers, local rentals and outstation trips across the valley.</p>
            <div class="mt-8">
                <x-search-widget :initialTab="'cabs'" />
            </div>
        </div>
    </section>
@endsection

@section('page')
<section class="shell py-10">
    <h2 class="section-title">Popular Routes</h2>
    <div class="mt-6 flex flex-wrap gap-3">
        @foreach (['Srinagar Airport → Gulmarg', 'Srinagar → Pahalgam', 'Srinagar → Sonamarg', 'Srinagar → Doodhpathri'] as $route)
            @php([$a, $b] = array_map('trim', explode('→', $route)))
            <a href="{{ route('cabs.search', ['pickup' => $a, 'drop' => $b, 'pickup_date' => now()->addDay()->format('Y-m-d'), 'pickup_time' => '09:00', 'trip_type' => 'one_way']) }}"
               class="btn-ghost btn-md">{{ $route }}</a>
        @endforeach
    </div>
</section>
@endsection

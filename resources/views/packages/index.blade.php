@extends('layouts.site')
@section('activeNav', 'packages')

@section('hero')
    <section class="hero">
        <div class="hero-media"><img src="{{ hero_banner('images/hero.svg') }}" alt="Kashmir tour packages"></div>
        <div class="hero-scrim"></div>
        <div class="shell relative pb-8 pt-14">
            <h1 class="font-display text-4xl font-extrabold text-ink-900">Kashmir Tour <span class="text-brand-600">Packages</span></h1>
            <p class="mt-2 max-w-lg text-[15px] text-ink-700">Handpicked experiences for every traveller — honeymoon, family, adventure and more.</p>
            <div class="mt-8">
                <x-search-widget :initialTab="'packages'" />
            </div>
        </div>
    </section>
@endsection

@section('page')
<section class="shell py-10">
    <form method="GET" class="card grid gap-3 p-4 sm:grid-cols-4">
        <input type="text" name="q" class="input" placeholder="Search packages…" value="{{ request('q') }}">
        <select name="destination" class="input">
            <option value="">All Destinations</option>
            @foreach ($destinations as $destination)
                <option value="{{ $destination->slug }}" @selected(request('destination') === $destination->slug)>{{ $destination->name }}</option>
            @endforeach
        </select>
        <select name="sort" class="input">
            @foreach (['' => 'Sort: Featured', 'price_low' => 'Price: Low to High', 'price_high' => 'Price: High to Low', 'duration' => 'Duration'] as $k => $label)
                <option value="{{ $k }}" @selected(request('sort') === $k)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn-primary btn-md">Search</button>
    </form>

    <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($packages as $package)
            <x-package-card :package="$package" />
        @empty
            <div class="card col-span-full p-12 text-center">
                <p class="font-display text-lg font-bold">No packages match your search</p>
                <a href="{{ route('packages.index') }}" class="btn-ghost btn-md mt-4">Clear Filters</a>
            </div>
        @endforelse
    </div>

    <div class="mt-8">{{ $packages->links() }}</div>
</section>
@endsection

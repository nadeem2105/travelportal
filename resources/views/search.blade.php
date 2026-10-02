@extends('layouts.site')

@section('page')
<x-track-event event="search" fb="Search" :data="['search_term' => $q]" />
<section class="shell pt-28 pb-12">
    <h1 class="font-display text-2xl font-bold">Search results for "{{ $q }}"</h1>

    @if ($packages->count())
        <h2 class="font-display mt-8 text-lg font-bold">Packages</h2>
        <div class="mt-3 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($packages as $package)
                <x-package-card :package="$package" />
            @endforeach
        </div>
    @endif

    @if ($destinations->count())
        <h2 class="font-display mt-8 text-lg font-bold">Destinations</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($destinations as $destination)
                <x-destination-card :destination="$destination" />
            @endforeach
        </div>
    @endif

    @if ($hotels->count())
        <h2 class="font-display mt-8 text-lg font-bold">Hotels</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($hotels as $hotel)
                <a href="{{ route('hotels.show', $hotel) }}" class="card card-hover flex items-center justify-between p-4">
                    <span class="font-bold text-ink-900">{{ $hotel->name }}</span>
                    <span class="text-xs text-ink-500">{{ $hotel->city }}</span>
                </a>
            @endforeach
        </div>
    @endif

    @if ($articles->count())
        <h2 class="font-display mt-8 text-lg font-bold">Guides &amp; Blog</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            @foreach ($articles as $article)
                <a href="{{ $article['type'] === 'Guide' ? route('guide.show', $article['slug']) : route('blog.show', $article['slug']) }}" class="card card-hover p-4">
                    <span class="badge-soft">{{ $article['type'] }}</span>
                    <p class="mt-2 text-sm font-bold text-ink-900">{{ $article['title'] }}</p>
                </a>
            @endforeach
        </div>
    @endif

    @if (! $packages->count() && ! $destinations->count() && ! $hotels->count() && ! $articles->count())
        <div class="card mt-8 p-12 text-center">
            <p class="font-display text-lg font-bold">Nothing found for "{{ $q }}"</p>
            <p class="mt-1 text-sm text-ink-500">Try "Srinagar", "honeymoon" or "Gulmarg".</p>
            <a href="{{ route('packages.index') }}" class="btn-primary btn-md mt-4">Browse All Packages</a>
        </div>
    @endif
</section>
@endsection

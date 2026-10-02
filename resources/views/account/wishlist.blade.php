@extends('layouts.site')

@section('page')
<x-account.shell :accountNav="'wishlist'">
    <h1 class="font-display text-2xl font-bold">My Wishlist</h1>

    @if ($packages->count())
        <h2 class="font-display mt-6 text-lg font-bold">Packages</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($packages as $package)
                <x-package-card :package="$package" />
            @endforeach
        </div>
    @endif

    @if ($hotels->count())
        <h2 class="font-display mt-8 text-lg font-bold">Hotels</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($hotels as $hotel)
                <a href="{{ route('hotels.show', $hotel) }}" class="card card-hover overflow-hidden">
                    <span class="block h-36 overflow-hidden">
                        <img src="{{ asset(img($hotel->cover_image, 'images/destinations/srinagar.svg')) }}" class="h-full w-full object-cover" alt="{{ $hotel->name }}">
                    </span>
                    <span class="block p-4">
                        <span class="font-display block text-sm font-bold">{{ $hotel->name }}</span>
                        <span class="text-xs text-ink-500">{{ $hotel->city }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    @if ($destinations->count())
        <h2 class="font-display mt-8 text-lg font-bold">Destinations</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-3 xl:grid-cols-6">
            @foreach ($destinations as $destination)
                <x-destination-card :destination="$destination" />
            @endforeach
        </div>
    @endif

    @if (! $packages->count() && ! $hotels->count() && ! $destinations->count())
        <div class="card mt-6 p-12 text-center">
            <p class="font-display font-bold">Your wishlist is empty</p>
            <p class="mt-1 text-sm text-ink-500">Tap the ♥ on any package or hotel to save it here.</p>
            <a href="{{ route('packages.index') }}" class="btn-primary btn-md mt-4">Browse Packages</a>
        </div>
    @endif
</x-account.shell>
@endsection

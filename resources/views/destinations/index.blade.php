@extends('layouts.site')
@section('activeNav', 'destinations')

@section('page')
<section class="shell pt-28">
    <h1 class="font-display text-3xl font-bold">Explore Kashmir</h1>
    <p class="section-sub mt-1">Popular destinations for your next trip</p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @forelse ($destinations as $destination)
            <a href="{{ route('destinations.show', $destination) }}" class="dest-card !h-56">
                <img src="{{ asset(img($destination->cover_image, 'images/destinations/' . $destination->slug . '.svg')) }}" alt="{{ $destination->name }}" loading="lazy">
                <span class="overlay"></span>
                <span class="absolute bottom-3 left-4 right-12 text-white">
                    <span class="font-display block text-lg font-bold">{{ $destination->name }}</span>
                    <span class="block text-xs text-slate-200">{{ $destination->famous_for }}</span>
                </span>
                <span class="dest-arrow">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </span>
            </a>
        @empty
            <p class="text-ink-500">Destinations coming soon.</p>
        @endforelse
    </div>

    <div class="mt-8">{{ $destinations->links() }}</div>
</section>
@endsection

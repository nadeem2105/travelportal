@extends('layouts.site')

@section('page')
<section class="shell max-w-5xl pt-28">
    <div class="overflow-hidden rounded-2xl">
        <img src="{{ asset(img($destination->cover_image, 'images/destinations/' . $destination->slug . '.svg')) }}" class="h-80 w-full object-cover" alt="{{ $destination->name }}">
    </div>

    <div class="mt-6 grid gap-8 lg:grid-cols-[1fr_320px]">
        <div>
            <h1 class="font-display text-3xl font-bold">{{ $destination->name }}</h1>
            <p class="mt-1 text-sm text-ink-500">{{ $destination->region }} @if($destination->altitude) · {{ $destination->altitude }} @endif @if($destination->best_time) · Best time: {{ $destination->best_time }} @endif</p>

            <div class="prose-page mt-5">
                {!! nl2br(e($destination->description ?? $destination->short_description)) !!}
            </div>

            @if ($destination->places_to_visit)
                <h2 class="mt-8 font-display text-xl font-bold">Places to Visit</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($destination->places_to_visit as $place)
                        <span class="badge-soft">{{ $place }}</span>
                    @endforeach
                </div>
            @endif

            @if ($destination->things_to_do)
                <h2 class="mt-8 font-display text-xl font-bold">Things to Do</h2>
                <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($destination->things_to_do as $thing)
                        <li class="flex gap-2 text-sm text-ink-700"><span class="text-brand-500">◆</span> {{ $thing }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($packages->count())
                <h2 class="mt-8 font-display text-xl font-bold">Packages for {{ $destination->name }}</h2>
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    @foreach ($packages as $package)
                        <x-package-card :package="$package" />
                    @endforeach
                </div>
            @endif
        </div>

        <aside class="h-fit lg:sticky lg:top-24">
            <div class="card p-5">
                <h3 class="font-display text-base font-bold">Plan Your Trip</h3>
                <p class="mt-1 text-xs text-ink-500">Handpicked stays in {{ $destination->name }}</p>
                <div class="mt-3 space-y-2">
                    @forelse ($hotels as $hotel)
                        <a href="{{ route('hotels.show', $hotel) }}" class="flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-semibold text-ink-700 transition hover:bg-brand-50 hover:text-brand-700">
                            {{ $hotel->name }} <span class="text-xs text-ink-500">from {{ money($hotel->starting_price ?? 0) }}</span>
                        </a>
                    @empty
                        <p class="text-xs text-ink-500">Hotels coming soon.</p>
                    @endforelse
                </div>
                <a href="{{ route('cabs.index') }}" class="btn-ghost btn-md mt-4 w-full">Book a Cab</a>
            </div>
        </aside>
    </div>
</section>
@endsection

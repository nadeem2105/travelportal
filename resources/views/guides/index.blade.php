@extends('layouts.site')
@section('activeNav', 'guide')

@section('page')
<section class="shell pt-28">
    <h1 class="font-display text-3xl font-bold">Kashmir Travel Guide</h1>
    <p class="section-sub mt-1">Everything you need to plan the perfect valley trip</p>

    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($guides as $guide)
            <a href="{{ route('guide.show', $guide) }}" class="card card-hover group overflow-hidden">
                <span class="block h-44 overflow-hidden">
                    <img src="{{ asset(img($guide->cover_image, 'images/guides/' . $guide->slug . '.svg')) }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" alt="{{ $guide->title }}" loading="lazy">
                </span>
                <span class="block p-5">
                    <span class="badge-soft">Travel Guide</span>
                    <span class="font-display mt-2 block text-base font-bold text-ink-900 group-hover:text-brand-700">{{ $guide->title }}</span>
                    <span class="mt-1 block text-sm text-ink-500">{{ \Illuminate\Support\Str::limit($guide->excerpt, 100) }}</span>
                </span>
            </a>
        @empty
            <p class="text-ink-500">Guides coming soon.</p>
        @endforelse
    </div>

    <div class="mt-8">{{ $guides->links() }}</div>
</section>
@endsection

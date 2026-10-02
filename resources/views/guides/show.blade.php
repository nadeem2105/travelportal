@extends('layouts.site')

@section('page')
<section class="shell max-w-3xl pt-28">
    <nav class="text-xs text-ink-500">
        <a href="{{ route('home') }}" class="hover:text-brand-700">Home</a> ›
        <a href="{{ route('guide.index') }}" class="hover:text-brand-700">Kashmir Guide</a>
    </nav>

    <h1 class="font-display mt-3 text-3xl font-bold">{{ $guide->title }}</h1>
    @if ($guide->excerpt)
        <p class="mt-2 text-sm text-ink-500">{{ $guide->excerpt }}</p>
    @endif
    @if ($guide->cover_image)
        <img src="{{ asset(img($guide->cover_image, 'images/guides/' . $guide->slug . '.svg')) }}" class="mt-5 h-72 w-full rounded-2xl object-cover" alt="{{ $guide->title }}">
    @endif

    <div class="prose-page mt-6">
        {!! nl2br(e($guide->content)) !!}
    </div>

    @if ($related->count())
        <h2 class="font-display mt-10 text-xl font-bold">Keep Reading</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            @foreach ($related as $item)
                <a href="{{ route('guide.show', $item) }}" class="card card-hover p-4 text-sm font-semibold text-ink-900 hover:text-brand-700">{{ \Illuminate\Support\Str::limit($item->title, 60) }}</a>
            @endforeach
        </div>
    @endif
</section>
@endsection

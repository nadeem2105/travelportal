@extends('layouts.site')

@section('page')
<section class="shell max-w-3xl pt-28">
    <h1 class="font-display text-3xl font-bold">Frequently Asked Questions</h1>

    @forelse ($faqs as $category => $items)
        <h2 class="font-display mt-8 text-lg font-bold capitalize">{{ str_replace('_', ' ', $category) }}</h2>
        <div class="mt-3 space-y-3">
            @foreach ($items as $faq)
                <details class="card p-0" @if($loop->parent->first && $loop->first) open @endif>
                    <summary class="cursor-pointer list-none p-4 font-semibold text-ink-900 transition hover:bg-brand-50/40">{{ $faq->question }}</summary>
                    <div class="border-t border-slate-100 p-4 text-sm leading-6 text-ink-700">{{ $faq->answer }}</div>
                </details>
            @endforeach
        </div>
    @empty
        <p class="mt-6 text-ink-500">FAQs coming soon.</p>
    @endforelse

    <div class="card mt-10 flex flex-wrap items-center justify-between gap-4 p-6">
        <div>
            <h2 class="font-display text-lg font-bold">Still have questions?</h2>
            <p class="text-sm text-ink-500">Our travel experts are happy to help, 24/7.</p>
        </div>
        <a href="{{ route('contact') }}" class="btn-primary btn-md">Contact Us</a>
    </div>
</section>
@endsection

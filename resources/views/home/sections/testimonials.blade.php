{{-- Testimonials section with carousel arrows --}}
<section class="shell py-10" x-data="{ track: null, scroll(dir) { this.track.scrollBy({ left: dir * 380, behavior: 'smooth' }) } }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="section-title">{{ $section->title ?? 'What Our Travellers Say' }}</h2>
        <div class="flex items-center gap-2">
            <a href="{{ route('reviews.index') }}" class="btn-pill-link">View All Reviews</a>
            <button type="button" @click="scroll(-1)" class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-ink-700 shadow-sm transition hover:border-brand-400 hover:text-brand-700" aria-label="Previous reviews">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 19.5-7.5-7.5 7.5-7.5"/></svg>
            </button>
            <button type="button" @click="scroll(1)" class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-ink-700 shadow-sm transition hover:border-brand-400 hover:text-brand-700" aria-label="Next reviews">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            </button>
        </div>
    </div>

    <div class="scroll-row mt-6 sm:grid sm:grid-cols-2 sm:overflow-visible lg:grid-cols-3" x-ref="track">
        @foreach ($testimonials as $testimonial)
            <div class="w-[340px] sm:w-auto">
                <x-testimonial-card :testimonial="$testimonial" />
            </div>
        @endforeach
    </div>
</section>

{{-- Frequently asked questions accordion --}}
@php($faqs = $faqs ?? collect())
@if ($faqs->count())
    <section class="shell py-10">
        @include('home.sections._header', [
            'title' => $section->title ?? 'Frequently Asked Questions',
            'subtitle' => $section->subtitle ?? 'Everything you need to know before you book',
            'cta' => false,
        ])
        <div class="mt-6 mx-auto max-w-3xl divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white" x-data="{ open: 0 }">
            @foreach ($faqs as $i => $faq)
                <div>
                    <button
                        type="button"
                        @click="open === {{ $i }} ? open = null : open = {{ $i }}"
                        class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left"
                        :aria-expanded="open === {{ $i }}"
                    >
                        <span class="font-display text-sm font-bold text-ink-900">{{ $faq->question }}</span>
                        <svg class="h-5 w-5 shrink-0 text-brand-600 transition-transform duration-200" :class="open === {{ $i }} ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    </button>
                    <div x-show="open === {{ $i }}" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0">
                        <p class="px-5 pb-5 text-sm leading-6 text-ink-600">{{ $faq->answer }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif

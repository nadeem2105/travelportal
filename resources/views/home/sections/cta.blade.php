{{-- Standalone Plan-My-Trip CTA band. Opens the global lead-capture modal. --}}
@php($waNumber = preg_replace('/\D+/', '', settings('company_whatsapp', settings('company_phone', '+917006976447'))))
<section class="shell py-10" x-data>
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand-600 to-brand-800 px-6 py-10 sm:px-10 sm:py-12">
        {{-- decorative mountains --}}
        <svg class="pointer-events-none absolute bottom-0 right-0 h-40 w-72 opacity-15 sm:h-52 sm:w-96" viewBox="0 0 320 160" fill="none">
            <path d="M0 160 L80 60 L120 100 L170 20 L230 110 L270 70 L320 160 Z" fill="#ffffff"/>
            <path d="M0 160 L60 120 L120 150 L180 110 L240 150 L320 120 L320 160 Z" fill="#ffffff" opacity="0.6"/>
        </svg>

        <div class="relative z-10 flex flex-col items-start justify-between gap-6 lg:flex-row lg:items-center">
            <div class="max-w-xl">
                <h2 class="font-display text-2xl font-extrabold text-white sm:text-3xl">
                    {{ $section->title ?? 'Not sure where to start?' }}
                </h2>
                <p class="mt-2 text-sm leading-6 text-brand-50">
                    {{ $section->subtitle ?? 'Tell us your dates and budget — our Kashmir travel expert will craft a custom itinerary and call you back with a free quote.' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <button
                    type="button"
                    @click="$dispatch('open-lead-modal', { source: 'home_cta', title: 'Plan My Trip' })"
                    class="inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 text-sm font-bold text-brand-700 shadow-float transition hover:bg-brand-50"
                >
                    {{ $section->cta_text ?? 'Get a Free Quote' }}
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                </button>
                @if ($waNumber)
                    <a
                        href="https://wa.me/{{ $waNumber }}?text={{ rawurlencode('Hi! I would like help planning a Kashmir trip.') }}"
                        target="_blank" rel="noopener"
                        class="inline-flex items-center gap-2 rounded-full border border-white/40 px-6 py-3 text-sm font-bold text-white transition hover:bg-white/10"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2a9.9 9.9 0 0 0-8.4 15.17L2 22l4.95-1.3A9.9 9.9 0 1 0 12.04 2Zm0 1.8a8.1 8.1 0 0 1 6.86 12.4l-.2.32.82 3-3.08-.81-.3.18a8.1 8.1 0 1 1-4.1-15.29Zm4.6 10.35c-.25-.13-1.47-.72-1.7-.8-.22-.09-.39-.13-.55.13-.16.25-.63.8-.78.96-.14.16-.28.18-.53.06-.25-.13-1.06-.39-2.02-1.24-.75-.67-1.25-1.5-1.4-1.75-.14-.25-.01-.38.11-.5.11-.11.25-.28.37-.42.13-.15.17-.25.25-.42.09-.16.04-.31-.02-.44-.06-.13-.55-1.33-.76-1.82-.2-.48-.4-.41-.55-.42h-.47c-.16 0-.42.06-.64.31-.22.25-.84.82-.84 2s.86 2.32.98 2.48c.13.16 1.7 2.6 4.12 3.64.57.25 1.02.4 1.37.5.58.19 1.1.16 1.51.1.46-.07 1.47-.6 1.68-1.18.2-.58.2-1.07.14-1.18-.06-.11-.22-.17-.47-.3Z"/></svg>
                        WhatsApp Us
                    </a>
                @endif
            </div>
        </div>
    </div>
</section>

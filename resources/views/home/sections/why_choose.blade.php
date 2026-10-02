{{-- Why choose us (2/3) + App promo (1/3) side-by-side per design --}}
<section class="shell py-10">
    <div class="grid gap-5 lg:grid-cols-[1.72fr_1fr]" id="download-app">
        {{-- Why choose card --}}
        <div class="card relative overflow-hidden p-7 sm:p-9">
            <div class="relative z-10">
                <h2 class="section-title">{{ $section->title ?? 'Why Choose ' . settings('company_name', 'Leemroz Travels') }}</h2>
                <div class="mt-7 grid gap-x-6 gap-y-7 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($features as $feature)
                        <div>
                            <span class="feature-icon">{!! $feature['icon'] !!}</span>
                            <h3 class="mt-3 text-sm font-bold text-ink-900">{{ $feature['title'] }}</h3>
                            <p class="mt-1 text-xs leading-5 text-ink-500">{{ $feature['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            @if (! empty($section?->image))
                {{-- Admin-managed decorative image (Admin → Homepage → Why Choose) --}}
                <img src="{{ img($section->image) }}" alt="" class="pointer-events-none absolute bottom-0 right-0 h-40 w-64 select-none object-contain object-right-bottom opacity-90 sm:h-48 sm:w-80" loading="lazy">
            @else
                {{-- decorative script + mountain (CSS-drawn, per design corner) --}}
                <div class="pointer-events-none absolute bottom-4 right-6 hidden select-none sm:block">
                    <p class="text-right font-script text-3xl leading-8 text-brand-300">{{ settings('why_script_line1', 'Explore') }}<br>{{ settings('why_script_line2', 'Book') }}<br>{{ settings('why_script_line3', 'Belong') }}</p>
                </div>
                <svg class="pointer-events-none absolute bottom-0 right-0 h-40 w-64 opacity-[0.16] sm:h-48 sm:w-80" viewBox="0 0 320 160" fill="none">
                    <path d="M0 160 L80 60 L120 100 L170 20 L230 110 L270 70 L320 160 Z" fill="#2563eb"/>
                    <path d="M170 20 L190 50 L150 50 Z M80 60 L95 82 L65 82 Z" fill="#1d4ed8"/>
                    <path d="M0 160 L60 120 L120 150 L180 110 L240 150 L320 120 L320 160 Z" fill="#3b82f6" opacity="0.7"/>
                </svg>
            @endif
        </div>

        {{-- App promo dark card --}}
        <div class="app-card p-7">
            <div class="relative z-10 flex h-full flex-col">
                <h2 class="font-display text-2xl font-bold">{{ $section->cta_text ?? 'Download Our App' }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-300">{{ $section->subtitle ?? 'Get exclusive offers on flights, hotels, cabs and packages.' }}</p>

                <div class="mt-5 flex flex-wrap gap-3">
                    @if (settings('app_android_url'))
                        <a href="{{ settings('app_android_url') }}" class="store-btn">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="m3.6 2.3 10.3 9.7L3.6 21.7c-.4-.2-.6-.6-.6-1V3.3c0-.4.2-.8.6-1Zm11.7 8.4 2.6-2.4 3.4 2c.9.5.9 1.9 0 2.4l-3.4 2-2.6-2.4v-1.6ZM4.9 1.5l8.9 8.3-2 1.9L4.9 1.5Zm0 21 6.9-10.2 2 1.9-8.9 8.3Z"/></svg>
                            <span class="text-left leading-tight"><span class="block text-[9px] uppercase tracking-wide text-slate-300">Get it on</span><span class="block text-sm font-bold">Google Play</span></span>
                        </a>
                    @endif
                    @if (settings('app_ios_url'))
                        <a href="{{ settings('app_ios_url') }}" class="store-btn">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M17.05 12.54c0-2.4 1.96-3.55 2.05-3.6-1.12-1.63-2.86-1.86-3.47-1.88-1.48-.15-2.88.87-3.63.87-.74 0-1.9-.85-3.12-.83-1.6.02-3.08.93-3.9 2.37-1.66 2.89-.42 7.17 1.2 9.51.8 1.15 1.74 2.44 2.98 2.4 1.2-.05 1.65-.78 3.1-.78 1.44 0 1.85.78 3.12.75 1.29-.02 2.1-1.17 2.89-2.32.9-1.32 1.27-2.6 1.3-2.67-.03-.01-2.5-.96-2.52-3.82ZM14.66 5.4c.66-.8 1.1-1.9.98-3.02-.95.04-2.1.63-2.78 1.43-.61.7-1.14 1.83-1 2.9 1.06.09 2.14-.53 2.8-1.31Z"/></svg>
                            <span class="text-left leading-tight"><span class="block text-[9px] uppercase tracking-wide text-slate-300">Download on the</span><span class="block text-sm font-bold">App Store</span></span>
                        </a>
                    @endif
                </div>

                <div class="mt-auto flex items-end justify-between gap-4 pt-6">
                    <img src="{{ asset(img(settings('app_qr_image', 'images/qr.svg'))) }}" class="h-24 w-24 rounded-xl bg-white p-1.5" alt="Scan to download app" loading="lazy">
                    {{-- phone mockup --}}
                    <div class="relative hidden w-28 rotate-6 sm:block">
                        <div class="rounded-[1.4rem] border-4 border-slate-700 bg-gradient-to-b from-brand-500 to-brand-800 p-2 pb-4 shadow-float">
                            <div class="mx-auto mb-2 h-1.5 w-10 rounded-full bg-slate-700"></div>
                            <div class="flex flex-col items-center gap-1.5 py-4">
                                <img src="{{ asset(img(settings('company_logo', 'images/logo.svg'))) }}" class="h-8 w-8" alt="logo">
                                <span class="text-[10px] font-bold text-white">{{ settings('company_short_name', 'Leemroz Travels') }}</span>
                                <span class="text-[6px] text-brand-100">{{ settings('company_tagline', 'Explore · Book · Experience') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

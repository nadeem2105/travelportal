{{-- Footer: dark navy with 4 columns + newsletter per design family --}}
<footer class="mt-14 bg-night text-slate-300">
    {{-- Newsletter strip --}}
    <div class="shell py-10">
        <div class="flex flex-col items-center justify-between gap-6 rounded-2xl bg-gradient-to-r from-brand-700 via-brand-600 to-brand-500 px-6 py-8 text-white shadow-float sm:px-10 lg:flex-row">
            <div>
                <h3 class="font-display text-2xl font-bold">{{ settings('newsletter_title', 'Get Travel Deals Before Anyone Else') }}</h3>
                <p class="mt-1 text-sm text-brand-100">{{ settings('newsletter_subtitle', 'Exclusive offers on flights, hotels, cabs and Kashmir packages.') }}</p>
            </div>
            <form action="{{ route('newsletter.subscribe') }}" method="POST" class="flex w-full max-w-md gap-2">
                @csrf
                <input type="email" name="email" required placeholder="Enter your email address"
                       class="w-full rounded-full border-0 bg-white/95 px-5 py-3 text-sm text-ink-900 outline-none placeholder:text-ink-300 focus:ring-2 focus:ring-white">
                <button class="btn bg-ink-900 px-6 py-3 text-sm font-semibold text-white hover:bg-black">Subscribe</button>
            </form>
        </div>
    </div>

    <div class="divider border-white/10"></div>

    <div class="shell grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <div class="flex items-center gap-2.5">
                <img src="{{ asset(img(settings('company_logo', 'images/logo.svg'))) }}" class="h-11 w-11" alt="logo">
                <span class="font-display text-lg font-bold text-white">{{ settings('company_name', 'Leemroz Travels') }}</span>
            </div>
            <p class="mt-4 max-w-sm text-sm leading-6 text-slate-400">
                {{ settings('footer_about', 'Discover the magic of Kashmir with flights, hotels, cabs and curated tour packages — all in one place. Explore · Book · Experience.') }}
            </p>
            <div class="mt-5 flex gap-3">
                @foreach (['facebook' => 'M13.5 9H16V6h-2.5C11.57 6 10 7.57 10 9.5V11H8v3h2v7h3v-7h2.1l.4-3H13v-1.5c0-.28.22-.5.5-.5Z', 'instagram' => 'M12 8.75a3.25 3.25 0 1 0 0 6.5 3.25 3.25 0 0 0 0-6.5ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm5.25-.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM12 4.5c-2.05 0-2.3.01-3.1.05-.8.03-1.35.16-1.83.35-.5.19-.92.45-1.34.87-.42.42-.68.84-.87 1.34-.19.48-.32 1.03-.35 1.83C4.47 9.74 4.46 9.99 4.46 12s.01 2.26.05 3.06c.03.8.16 1.35.35 1.83.19.5.45.92.87 1.34.42.42.84.68 1.34.87.48.19 1.03.32 1.83.35.8.04 1.05.05 3.1.05s2.3-.01 3.1-.05c.8-.03 1.35-.16 1.83-.35.5-.19.92-.45 1.34-.87.42-.42.68-.84.87-1.34.19-.48.32-1.03.35-1.83.04-.8.05-1.05.05-3.06s-.01-2.26-.05-3.06c-.03-.8-.16-1.35-.35-1.83a3.7 3.7 0 0 0-.87-1.34 3.7 3.7 0 0 0-1.34-.87c-.48-.19-1.03-.32-1.83-.35-.8-.04-1.05-.05-3.1-.05Z', 'youtube' => 'M21.6 7.2a2.5 2.5 0 0 0-1.76-1.77C18.25 5 12 5 12 5s-6.25 0-7.84.43A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.76 1.77C5.75 19 12 19 12 19s6.25 0 7.84-.43a2.5 2.5 0 0 0 1.76-1.77A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8ZM10 15V9l5.2 3L10 15Z'] as $icon => $path)
                    @if (settings('social_' . $icon))
                        <a href="{{ settings('social_' . $icon) }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($icon) }}"
                           class="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-brand-600">
                            <svg class="h-4.5 w-4.5" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="{{ $path }}"/></svg>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>

        <div>
            <h4 class="font-display text-sm font-bold uppercase tracking-wider text-white">Quick Links</h4>
            <ul class="mt-4 space-y-2.5 text-sm">
                @forelse (menu_tree('footer_quick')->count() ? menu_tree('footer_quick') : collect([
                    (object)['label' => 'Popular Packages', 'url' => route('packages.index')],
                    (object)['label' => 'Destinations', 'url' => route('destinations.index')],
                    (object)['label' => 'Offers & Coupons', 'url' => route('offers.index')],
                    (object)['label' => 'Kashmir Guide', 'url' => route('guide.index')],
                    (object)['label' => 'Blog', 'url' => route('blog.index')],
                ]) as $item)
                    <li><a href="{{ $item->url }}" class="transition hover:text-white">{{ $item->label }}</a></li>
                @empty
                @endforelse
            </ul>
        </div>

        <div>
            <h4 class="font-display text-sm font-bold uppercase tracking-wider text-white">Support</h4>
            <ul class="mt-4 space-y-2.5 text-sm">
                @forelse (menu_tree('footer_support')->count() ? menu_tree('footer_support') : collect([
                    (object)['label' => 'Contact Us', 'url' => route('contact')],
                    (object)['label' => 'FAQs', 'url' => route('faq')],
                    (object)['label' => 'Support / Tickets', 'url' => route('support.index')],
                    (object)['label' => 'Cancellation & Refund', 'url' => route('page.show', 'cancellation-refund')],
                    (object)['label' => 'Terms & Conditions', 'url' => route('page.show', 'terms')],
                ]) as $item)
                    <li><a href="{{ $item->url }}" class="transition hover:text-white">{{ $item->label }}</a></li>
                @empty
                @endforelse
                {{-- Always shown, regardless of DB-configured support menu --}}
                <li><a href="{{ route('agent.apply') }}" class="transition hover:text-white">Become a Travel Agent</a></li>
            </ul>
        </div>

        <div>
            <h4 class="font-display text-sm font-bold uppercase tracking-wider text-white">Contact</h4>
            <ul class="mt-4 space-y-3 text-sm">
                <li class="flex gap-2.5">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                    <span>{{ settings('company_address', 'Ishber Nishat Gupt Ganga, Srinagar J&K -190025') }}</span>
                </li>
                <li class="flex gap-2.5">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
                    <a href="tel:{{ preg_replace('/\s+/', '', settings('company_phone', '+917006976447')) }}" class="hover:text-white">{{ settings('company_phone', '+91 70069 76447') }}</a>
                </li>
                <li class="flex gap-2.5">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
                    <a href="mailto:{{ settings('company_email', 'hello@leemroztravels.com') }}" class="hover:text-white">{{ settings('company_email', 'hello@leemroztravels.com') }}</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="divider border-white/10"></div>
    <div class="shell flex flex-col items-center justify-between gap-3 py-5 text-xs text-slate-500 sm:flex-row">
        <p>&copy; {{ now()->year }} {{ settings('company_name', 'Leemroz Travels') }}. {{ settings('footer_copyright', 'All rights reserved.') }}</p>
        <div class="flex items-center gap-4">
            @foreach (menu_tree('footer_legal')->count() ? menu_tree('footer_legal') : collect([
                (object)['label' => 'Privacy Policy', 'url' => route('page.show', 'privacy')],
                (object)['label' => 'Terms', 'url' => route('page.show', 'terms')],
                (object)['label' => 'Cookie Policy', 'url' => route('page.show', 'cookie-policy')],
            ]) as $item)
                <a href="{{ $item->url }}" class="hover:text-white">{{ $item->label }}</a>
            @endforeach
        </div>
    </div>
</footer>

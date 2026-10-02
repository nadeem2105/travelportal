{{-- Floating white pill header per approved design --}}
<header class="header-shell">
    <div class="header-pill !px-3 sm:!px-5">
        {{-- Logo --}}
        <a href="{{ route('home') }}" class="flex min-w-0 shrink items-center gap-2">
            <img src="{{ asset(img(settings('company_logo', 'images/logo.svg'))) }}" alt="{{ settings('company_name', 'Leemroz Travels') }}" class="h-9 w-9 sm:h-11 sm:w-11">
            <span class="hidden min-[480px]:block leading-tight">
                <span class="font-display block whitespace-nowrap text-[16px] font-bold text-ink-900 sm:text-[19px]">{{ settings('company_name', 'Leemroz Travels') }}</span>
                <span class="hidden sm:block text-[10.5px] font-semibold tracking-wide text-brand-600">{{ settings('company_tagline', 'Explore · Book · Experience') }}</span>
            </span>
        </a>

        {{-- Desktop nav (admin-managed, fallback defaults) --}}
        <nav class="hidden items-center gap-6 lg:flex">
            @forelse (menu_tree('header') as $item)
                <a href="{{ $item->url }}"
                   class="nav-link {{ url()->current() === url($item->url) ? 'active' : '' }}"
                   @if(($item->target ?? '_self') !== '_self') target="{{ $item->target }}" @endif>
                    {{ $item->label }}
                </a>
            @empty
                <a href="{{ route('home') }}" class="nav-link {{ ($activeNav ?? '') === 'home' ? 'active' : '' }}">Home</a>
                <a href="{{ route('flights.index') }}" class="nav-link {{ ($activeNav ?? '') === 'flights' ? 'active' : '' }}">Flights</a>
                <a href="{{ route('hotels.index') }}" class="nav-link {{ ($activeNav ?? '') === 'hotels' ? 'active' : '' }}">Hotels</a>
                <a href="{{ route('cabs.index') }}" class="nav-link {{ ($activeNav ?? '') === 'cabs' ? 'active' : '' }}">Cabs</a>
                <a href="{{ route('packages.index') }}" class="nav-link {{ ($activeNav ?? '') === 'packages' ? 'active' : '' }}">Packages</a>
                <a href="{{ route('offers.index') }}" class="nav-link {{ ($activeNav ?? '') === 'offers' ? 'active' : '' }}">Offers</a>
                <a href="{{ route('guide.index') }}" class="nav-link {{ ($activeNav ?? '') === 'guide' ? 'active' : '' }}">Kashmir Guide</a>
            @endforelse
        </nav>

        {{-- Right actions --}}
        <div class="flex items-center gap-3">
            <div x-data="{ open: false, q: '' }" class="hidden lg:block">
                <button type="button" @click="open = true; $nextTick(() => $refs.q.focus())" aria-label="Search"
                        class="flex h-10 w-10 items-center justify-center rounded-full text-ink-700 transition hover:bg-slate-100 hover:text-brand-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z"/></svg>
                </button>
                {{-- Search overlay --}}
                <div x-cloak x-show="open" @keydown.escape.window="open = false" class="fixed inset-0 z-[90] flex items-start justify-center pt-24">
                    <div class="absolute inset-0 bg-ink-900/50 backdrop-blur-sm" @click="open = false"></div>
                    <form action="{{ route('search') }}" method="GET" class="relative z-10 w-full max-w-xl px-4"
                          @submit="if(!q.trim()){ $event.preventDefault(); }">
                        <div class="flex items-center gap-2 rounded-2xl bg-white p-2 shadow-float">
                            <svg class="ml-2 h-5 w-5 text-ink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z"/></svg>
                            <input x-ref="q" x-model="q" name="q" type="search" placeholder="Search packages, destinations, hotels…" class="flex-1 border-0 bg-transparent text-sm focus:outline-none focus:ring-0">
                            <button type="submit" class="btn-primary btn-sm">Search</button>
                        </div>
                    </form>
                </div>
            </div>

            <a href="tel:{{ preg_replace('/\s+/', '', settings('company_phone', '+917006976447')) }}" class="hidden items-center gap-2.5 xl:flex">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-brand-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
                </span>
                <span class="leading-tight">
                    <span class="block text-sm font-bold text-ink-900">{{ settings('company_phone', '+91 70069 76447') }}</span>
                    <span class="block text-[11px] text-ink-500">24/7 Support</span>
                </span>
            </a>

            {{-- Primary conversion CTA: opens the lead modal (never auto-opens) --}}
            <button type="button" x-data
                    @click="$dispatch('open-lead-modal', { source: 'header', title: 'Plan My Trip' })"
                    class="btn-primary btn-md !px-3.5 md:!px-5">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6"/></svg>
                <span class="hidden sm:inline">Get Quote</span><span class="sm:hidden">Quote</span>
            </button>

            <div class="hidden xl:block">
            <a href="{{ settings('app_download_url', '#download-app') }}" class="btn-ghost btn-md !px-3.5 md:!px-4">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-2.25-6H3.75m15.75 0H21M3.75 18h15.75M12 6.75h.008v.008H12V6.75Zm0 11.25h.008v.008H12V18ZM3 3h18v18H3V3Z"/></svg>
                <span>App</span>
            </a>
            </div>

            {{-- Account --}}
            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open = !open" class="flex h-10 w-10 items-center justify-center rounded-full text-ink-700 transition hover:bg-slate-100 hover:text-brand-700" aria-label="Account">
                    <svg class="h-5.5 w-5.5" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                </button>
                <div x-cloak x-show="open" @click.outside="open = false" x-transition
                     class="absolute right-0 top-12 w-56 overflow-hidden rounded-2xl bg-white py-2 shadow-float ring-1 ring-slate-900/10">
                    @auth('web')
                        <div class="px-4 py-2">
                            <p class="truncate text-sm font-bold text-ink-900">{{ auth('web')->user()->name }}</p>
                            <p class="truncate text-xs text-ink-500">{{ auth('web')->user()->email }}</p>
                        </div>
                        <div class="divider my-1"></div>
                        <a href="{{ route('account.dashboard') }}" class="block px-4 py-2 text-sm font-medium hover:bg-brand-50 hover:text-brand-700">My Account</a>
                        <a href="{{ route('account.trips') }}" class="block px-4 py-2 text-sm font-medium hover:bg-brand-50 hover:text-brand-700">My Trips</a>
                        <a href="{{ route('account.wishlist') }}" class="block px-4 py-2 text-sm font-medium hover:bg-brand-50 hover:text-brand-700">Wishlist</a>
                        <div class="divider my-1"></div>
                        <a href="{{ route('account.logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="block px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">Sign Out</a>
                    @else
                        <a href="{{ route('login') }}" class="block px-4 py-2 text-sm font-medium hover:bg-brand-50 hover:text-brand-700">Sign In</a>
                        <a href="{{ route('register') }}" class="block px-4 py-2 text-sm font-medium hover:bg-brand-50 hover:text-brand-700">Create Account</a>
                        <div class="my-1 border-t border-slate-100"></div>
                        <a href="{{ route('agent.login') }}" class="block px-4 py-2 text-sm font-medium text-ink-500 hover:bg-brand-50 hover:text-brand-700">Agent Portal</a>
                    @endauth
                </div>
            </div>

            {{-- Mobile menu toggle --}}
            <div class="relative" x-data="{ nav: false }">
                <button type="button" @click="nav = !nav" class="flex h-10 w-10 items-center justify-center rounded-full text-ink-700 hover:bg-slate-100 lg:hidden" aria-label="Menu">
                    <svg x-show="!nav" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                    <svg x-cloak x-show="nav" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
                <div x-cloak x-show="nav" @click.outside="nav = false" x-transition.opacity.duration.150ms
                     class="fixed inset-x-3 top-[72px] z-50 rounded-2xl bg-white p-3 shadow-float ring-1 ring-slate-900/10 lg:hidden">
                    <nav class="grid gap-1">
                        @php($mobileMenu = menu_tree('header'))
                        @forelse ($mobileMenu->count() ? $mobileMenu : collect([
                            (object)['label' => 'Home', 'url' => route('home')],
                            (object)['label' => 'Flights', 'url' => route('flights.index')],
                            (object)['label' => 'Hotels', 'url' => route('hotels.index')],
                            (object)['label' => 'Cabs', 'url' => route('cabs.index')],
                            (object)['label' => 'Packages', 'url' => route('packages.index')],
                            (object)['label' => 'Offers', 'url' => route('offers.index')],
                            (object)['label' => 'Kashmir Guide', 'url' => route('guide.index')],
                        ]) as $item)
                            <a href="{{ $item->url }}" class="rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-brand-50 hover:text-brand-700">{{ $item->label }}</a>
                        @empty
                            <a href="{{ route('home') }}" class="rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-brand-50 hover:text-brand-700">Home</a>
                        @endforelse

                        <div class="my-1 border-t border-slate-100"></div>
                        @auth('web')
                            <a href="{{ route('account.dashboard') }}" class="rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-brand-50 hover:text-brand-700">My Account</a>
                            <a href="{{ route('account.trips') }}" class="rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-brand-50 hover:text-brand-700">My Trips</a>
                            <a href="{{ route('account.wishlist') }}" class="rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-brand-50 hover:text-brand-700">Wishlist</a>
                        @else
                            <a href="{{ route('login') }}" class="rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-brand-50 hover:text-brand-700">Sign In</a>
                            <a href="{{ route('register') }}" class="rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-brand-50 hover:text-brand-700">Create Account</a>
                        @endauth

                        <button type="button" @click="nav = false; $dispatch('open-lead-modal', { source: 'mobile_nav' })" class="btn-primary btn-md mt-2 w-full justify-center">Get Free Quote</button>
                        <a href="tel:{{ preg_replace('/\s+/', '', settings('company_phone', '+917006976447')) }}" class="btn-ghost btn-md mt-1 w-full justify-center">Call {{ settings('company_phone', '+91 70069 76447') }}</a>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    @auth('web')
        <form id="logout-form" action="{{ route('account.logout') }}" method="POST" class="hidden">@csrf</form>
    @endauth
</header>

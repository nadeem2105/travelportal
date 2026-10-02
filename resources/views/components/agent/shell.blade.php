{{-- Agent portal sidebar used by all agent pages --}}
@php
    $agentNav = $agentNav ?? '';
    $agent = auth('agent')->user();
    $links = [
        'dashboard' => ['Dashboard', 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z'],
        'packages' => ['Book Packages', 'M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5'],
        'bookings' => ['My Bookings', 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z'],
        'wallet' => ['Wallet & Ledger', 'M21 12a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 12m18 0v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 9m18 0V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v3'],
        'commission' => ['Commission', 'M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941'],
        'profile' => ['Profile & KYC', 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z'],
    ];
@endphp

<div class="shell grid gap-6 py-10 lg:grid-cols-[250px_1fr]">
    <aside class="h-fit">
        <div class="card p-4">
            <div class="flex items-center gap-3 px-2 py-2">
                @if ($agent->logoUrl())
                    <img src="{{ $agent->logoUrl() }}" class="h-11 w-11 rounded-full object-cover" alt="logo">
                @else
                    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">{{ strtoupper(substr($agent->agency_name, 0, 2)) }}</span>
                @endif
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-ink-900">{{ $agent->agency_name }}</p>
                    <p class="truncate text-xs text-ink-500">{{ $agent->agency_code }}</p>
                </div>
            </div>
            <nav class="mt-3 space-y-0.5">
                @foreach ($links as $key => [$label, $path])
                    <a href="{{ route('agent.' . $key) }}"
                       class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ $agentNav === $key ? 'bg-brand-600 text-white' : 'text-ink-700 hover:bg-brand-50 hover:text-brand-700' }}">
                        <svg class="h-4.5 w-4.5" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
                        {{ $label }}
                    </a>
                @endforeach
                <form action="{{ route('agent.logout') }}" method="POST" class="pt-1">
                    @csrf
                    <button class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-semibold text-ink-700 transition hover:bg-rose-50 hover:text-rose-600">
                        <svg class="h-4.5 w-4.5" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
                        Sign Out
                    </button>
                </form>
            </nav>
        </div>
    </aside>

    <div>
        {{ $slot }}
    </div>
</div>

@extends('layouts.site')

@section('page')
<x-agent.shell agentNav="packages">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold">Book Packages</h1>
        <p class="mt-1 text-sm text-ink-500">Prices shown include your agent commission of {{ rtrim(rtrim(number_format($agent->commission_rate, 2), '0'), '.') }}%.</p>
    </div>

    <form method="GET" class="mb-6 flex flex-wrap gap-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search packages…" class="input max-w-xs">
        <select name="destination" class="input max-w-[200px]">
            <option value="">All destinations</option>
            @foreach ($destinations as $d)
                <option value="{{ $d->slug }}" @selected(request('destination') === $d->slug)>{{ $d->name }}</option>
            @endforeach
        </select>
        <select name="sort" class="input max-w-[180px]">
            <option value="">Sort: Featured</option>
            <option value="price_low" @selected(request('sort') === 'price_low')>Price: Low to High</option>
            <option value="price_high" @selected(request('sort') === 'price_high')>Price: High to Low</option>
        </select>
        <button class="btn-primary">Filter</button>
    </form>

    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($packages as $package)
            @php
                $public = $package->effectivePrice(now()->addDays(14)->format('Y-m-d'));
                $agentNet = $public * (1 - (float) $agent->commission_rate / 100);
            @endphp
            <div class="card overflow-hidden">
                <img src="{{ img($package->cover_image) }}" class="h-40 w-full object-cover" alt="{{ $package->name }}">
                <div class="p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-600">{{ $package->destination->name ?? 'Package' }}</p>
                    <h3 class="mt-1 line-clamp-2 font-display text-base font-bold">{{ $package->name }}</h3>
                    <p class="mt-1 text-xs text-ink-500">{{ $package->duration_days }} Days / {{ $package->duration_nights ?? max(0, $package->duration_days - 1) }} Nights</p>
                    <div class="mt-3 flex items-end justify-between">
                        <div>
                            <p class="text-xs text-ink-400 line-through">{{ money($public) }}</p>
                            <p class="text-lg font-bold text-ink-900">{{ money($agentNet) }}<span class="text-xs font-normal text-ink-400">/adult net</span></p>
                        </div>
                        <a href="{{ route('agent.packages.create', $package) }}" class="btn-primary btn-sm">Book</a>
                    </div>
                </div>
            </div>
        @empty
            <p class="col-span-full py-12 text-center text-ink-400">No packages found.</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $packages->links() }}</div>
</x-agent.shell>
@endsection

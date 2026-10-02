@extends('layouts.site')

@section('page')
<section class="shell py-10">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold">{{ $params['pickup'] }} → {{ $params['drop'] }}</h1>
            <p class="mt-1 text-sm text-ink-500">{{ ucfirst(str_replace('_', ' ', $params['trip_type'])) }} · {{ $distance }} km approx · {{ \Carbon\Carbon::parse($params['pickup_datetime'])->format('d M Y, h:i A') }}</p>
        </div>
        <a href="{{ route('cabs.index') }}" class="btn-ghost btn-md">Modify Search</a>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        @forelse ($vehicles as $vehicle)
            <article class="card card-hover p-4">
                <div class="flex gap-4">
                    <div class="h-24 w-32 shrink-0 overflow-hidden rounded-xl bg-brand-50">
                        <img src="{{ asset(img($vehicle['image'], 'images/logo.svg')) }}" class="h-full w-full object-cover" alt="{{ $vehicle['name'] }}" loading="lazy">
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-display text-base font-bold text-ink-900">{{ $vehicle['name'] }}</h2>
                        <p class="text-xs text-ink-500">{{ $vehicle['type'] }} {{ $vehicle['is_ac'] ? '· AC' : '' }}</p>
                        <div class="mt-1 flex gap-3 text-xs text-ink-500">
                            <span>👤 {{ $vehicle['passenger_capacity'] }} seats</span>
                            <span>🧳 {{ $vehicle['luggage_capacity'] }} bags</span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 flex items-end justify-between border-t border-slate-100 pt-3">
                    <div>
                        <p class="font-display text-xl font-extrabold text-ink-900">{{ money($vehicle['display_price']) }}</p>
                        <p class="text-[11px] text-ink-500">inclusive of taxes &amp; fees</p>
                    </div>
                    <form action="{{ route('cabs.details') }}" method="GET">
                        <input type="hidden" name="vehicle_id" value="{{ $vehicle['vehicle_id'] }}">
                        <button class="btn-primary btn-md">Book Now</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="card p-12 text-center sm:col-span-2">
                <p class="font-display text-lg font-bold">No cabs available on this route right now.</p>
                <a href="{{ route('cabs.index') }}" class="btn-primary btn-md mt-4">Try Another Route</a>
            </div>
        @endforelse
    </div>
</section>
@endsection

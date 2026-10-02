@extends('layouts.site')

@section('hero')
    {{-- Compact page head with route summary --}}
    <section class="bg-gradient-to-r from-brand-700 to-brand-500 pt-28 pb-10 text-white">
        <div class="shell">
            <h1 class="font-display text-2xl font-bold sm:text-3xl">
                {{ $params['from'] }} → {{ $params['to'] }}
                <span class="ml-2 rounded-full bg-white/20 px-3 py-1 align-middle text-xs font-semibold uppercase">{{ $params['trip_type'] === 'round_trip' ? 'Round Trip' : 'One Way' }}</span>
            </h1>
            <p class="mt-1 text-sm text-brand-100">
                {{ \Carbon\Carbon::parse($params['departure'])->format('D, d M Y') }}
                @if($params['trip_type'] === 'round_trip') — Return {{ \Carbon\Carbon::parse($params['return'])->format('D, d M Y') }} @endif
                · {{ $params['adults'] }} Traveller(s) · {{ ucfirst($params['cabin_class']) }}
            </p>
        </div>
    </section>
@endsection

@section('page')
<section class="shell grid gap-6 py-8 lg:grid-cols-[280px_1fr]" x-data="{ loading: false }">
    {{-- Filters sidebar --}}
    <aside class="h-fit lg:sticky lg:top-24">
        <form method="GET" action="{{ route('flights.results') }}" @submit="loading = true" class="card space-y-5 p-5">
            @foreach (['from', 'to', 'departure', 'return', 'travellers'] as $k)
                @if($params[$k] ?? false)
                    <input type="hidden" name="{{ $k }}" value="{{ $params[$k] }}">
                @endif
            @endforeach
            <input type="hidden" name="sort" value="{{ request('sort', 'recommended') }}">

            <div>
                <h3 class="mb-2 text-sm font-bold text-ink-900">Price Range</h3>
                <input type="range" name="max_price" min="{{ $filters['min_price'] }}" max="{{ $filters['max_price'] }}"
                       value="{{ $filters['max_price'] }}" class="w-full accent-brand-600"
                       oninput="this.nextElementSibling.textContent = 'Up to ₹' + Number(this.value).toLocaleString('en-IN')">
                <p class="text-xs text-ink-500">Up to ₹{{ number_format($filters['max_price']) }}</p>
            </div>

            <div>
                <h3 class="mb-2 text-sm font-bold text-ink-900">Airlines</h3>
                <div class="space-y-2">
                    @foreach ($filters['airlines'] as $name => $info)
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-700">
                            <input type="checkbox" name="airline[]" value="{{ $info['code'] }}" class="accent-brand-600" @checked(in_array($info['code'], (array) request('airline')))>
                            {{ $name }} <span class="text-xs text-ink-300">({{ $info['count'] }})</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <h3 class="mb-2 text-sm font-bold text-ink-900">Stops</h3>
                <div class="space-y-2">
                    @foreach ($filters['stops'] as $stops)
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-700">
                            <input type="radio" name="stops" value="{{ $stops }}" class="accent-brand-600" @checked(request('stops') === (string) $stops)>
                            {{ $stops === 0 ? 'Non-stop' : $stops . ' Stop' }}
                        </label>
                    @endforeach
                </div>
            </div>

            <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-700">
                <input type="checkbox" name="refundable" value="1" class="accent-brand-600" @checked(request()->boolean('refundable'))>
                Refundable fares only
            </label>

            <button class="btn-primary btn-md w-full">Apply Filters</button>
            <a href="{{ route('flights.results', collect($params)->toArray()) }}" class="block text-center text-xs text-ink-500 hover:text-brand-700">Reset</a>
        </form>
    </aside>

    {{-- Results --}}
    <div>
        @if (session('error'))
            <div class="alert-error mb-4">{{ session('error') }}</div>
        @endif
        @if ($hasSupplierErrors)
            <div class="alert-warn mb-4">We're unable to retrieve availability from some partners right now. Showing the results we have.</div>
        @endif

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-ink-500"><span class="font-bold text-ink-900">{{ $count }}</span> flights found</p>
            <div class="flex gap-2">
                @foreach (['recommended' => 'Recommended', 'cheapest' => 'Cheapest', 'fastest' => 'Fastest', 'earliest' => 'Earliest'] as $k => $label)
                    <a href="{{ route('flights.results', array_merge($params, ['sort' => $k] + request()->only(['airline', 'max_price', 'stops', 'refundable']))) }}"
                       @click="loading = true"
                       class="rounded-full px-4 py-1.5 text-xs font-semibold transition {{ request('sort', 'recommended') === $k ? 'bg-brand-600 text-white' : 'bg-white text-ink-700 ring-1 ring-slate-200 hover:text-brand-700' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Animated Skeleton Loaders for Perceived Performance --}}
        <div x-cloak x-show="loading" class="space-y-4">
            @for ($s = 0; $s < 3; $s++)
                <div class="card p-5 animate-pulse">
                    <div class="flex flex-wrap items-center gap-4 sm:gap-8">
                        <div class="flex min-w-[150px] items-center gap-3">
                            <div class="h-10 w-10 rounded-full bg-slate-200"></div>
                            <div class="space-y-2">
                                <div class="h-4 w-24 rounded bg-slate-200"></div>
                                <div class="h-3 w-16 rounded bg-slate-100"></div>
                            </div>
                        </div>
                        <div class="flex flex-1 items-center justify-between gap-4">
                            <div class="space-y-1">
                                <div class="h-6 w-16 rounded bg-slate-200"></div>
                                <div class="h-3 w-12 rounded bg-slate-100"></div>
                            </div>
                            <div class="flex-1 max-w-[140px] space-y-1 text-center">
                                <div class="h-3 w-14 rounded bg-slate-100 mx-auto"></div>
                                <div class="h-1 w-full rounded bg-slate-200"></div>
                                <div class="h-3 w-12 rounded bg-slate-100 mx-auto"></div>
                            </div>
                            <div class="space-y-1 text-right">
                                <div class="h-6 w-16 rounded bg-slate-200 ml-auto"></div>
                                <div class="h-3 w-12 rounded bg-slate-100 ml-auto"></div>
                            </div>
                        </div>
                        <div class="min-w-[140px] space-y-2 text-right">
                            <div class="h-6 w-20 rounded bg-slate-200 ml-auto"></div>
                            <div class="h-9 w-24 rounded-xl bg-brand-200 ml-auto"></div>
                        </div>
                    </div>
                </div>
            @endfor
        </div>

        <div class="space-y-4" x-show="!loading">
            @forelse ($flights as $flight)
                <article class="card card-hover p-5">
                    <div class="flex flex-wrap items-center gap-4 sm:gap-8">
                        {{-- Airline --}}
                        <div class="flex min-w-[150px] items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-50 text-xs font-extrabold text-brand-700">{{ $flight['airline']['code'] }}</span>
                            <div>
                                <p class="text-sm font-bold text-ink-900">{{ $flight['airline']['name'] }}</p>
                                <p class="text-xs text-ink-500">{{ $flight['flight_number'] }} · {{ ucfirst($flight['cabin_class']) }}</p>
                            </div>
                        </div>

                        {{-- Route --}}
                        <div class="flex flex-1 items-center justify-between gap-3">
                            <div>
                                <p class="font-display text-lg font-bold text-ink-900">{{ $flight['segments'][0]['from']['time'] }}</p>
                                <p class="text-xs text-ink-500">{{ $flight['segments'][0]['from']['code'] }}</p>
                            </div>
                            <div class="flex-1 px-2 text-center">
                                <p class="text-[11px] font-semibold text-ink-500">{{ floor($flight['segments'][0]['duration_minutes'] / 60) }}h {{ $flight['segments'][0]['duration_minutes'] % 60 }}m</p>
                                <div class="relative my-1">
                                    <div class="h-px w-full bg-slate-200"></div>
                                    <span class="absolute -top-1 left-1/2 h-2 w-2 -translate-x-1/2 rounded-full bg-brand-500"></span>
                                </div>
                                <p class="text-[11px] {{ $flight['stops'] === 0 ? 'text-emerald-600' : 'text-amber-600' }}">
                                    {{ $flight['stops'] === 0 ? 'Non-stop' : $flight['stops'] . ' Stop · ' . ($flight['segments'][0]['stopover']['airport'] ?? '') }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-display text-lg font-bold text-ink-900">{{ $flight['segments'][0]['to']['time'] }}</p>
                                <p class="text-xs text-ink-500">{{ $flight['segments'][0]['to']['code'] }}</p>
                            </div>
                        </div>

                        {{-- Price + CTA --}}
                        <div class="w-full border-t border-slate-100 pt-3 sm:w-auto sm:border-0 sm:pt-0">
                            <p class="font-display text-xl font-extrabold text-ink-900">{{ money($flight['fare']['total_for_all']) }}</p>
                            <p class="text-[11px] text-ink-500">
                                @if ($flight['fare']['total_for_all'] > $flight['fare']['display_total'])
                                    {{ money($flight['fare']['display_total']) }} per traveller
                                @else
                                    {{ money($flight['fare']['display_total']) }} per traveller
                                @endif
                            </p>
                            <form action="{{ route('flights.select') }}" method="POST" class="mt-2">
                                @csrf
                                <input type="hidden" name="result_id" value="{{ $flight['result_id'] }}">
                                <input type="hidden" name="baggage" value="">
                                <button class="btn-primary btn-md w-full sm:w-auto">Select Flight</button>
                            </form>
                            <p class="mt-1.5 text-center text-[11px] {{ $flight['refundable'] ? 'text-emerald-600' : 'text-rose-500' }} sm:text-left">
                                {{ $flight['refundable'] ? '✓ Refundable' : '✕ Non-refundable' }} · {{ $flight['segments'][0]['baggage']['check_in'] ?? '15 Kg' }}
                            </p>
                        </div>
                    </div>
                </article>
            @empty
                <div class="card p-12 text-center">
                    <p class="font-display text-lg font-bold text-ink-900">No flights found</p>
                    <p class="mt-1 text-sm text-ink-500">Try different dates or a nearby airport.</p>
                    <a href="{{ route('flights.index') }}" class="btn-primary btn-md mt-4">Modify Search</a>
                </div>
            @endforelse
        </div>
    </div>
</section>
@endsection

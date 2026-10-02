{{--
    Booking widget: tabs (Flights / Hotels / Cabs / Packages) + fields,
    per approved design. Each tab is a GET form to its engine.
    $initialTab = flights|hotels|cabs|packages
--}}
@php
    $initialTab = $initialTab ?? 'flights';
    $airports = $airports ?? \App\Models\Airport::active()->get();
    $destinations = $destinations ?? \App\Models\Destination::where('status', 'active')->orderBy('sort_order')->limit(20)->get();
    $locations = $locations ?? \App\Models\CabLocation::where('status', 'active')->orderBy('sort_order')->get();
@endphp

<div class="search-card" x-data="searchWidget('{{ $initialTab }}')" id="booking-widget">
    {{-- Tabs --}}
    <div class="flex flex-wrap gap-3 pb-4">
        <button type="button" class="search-tab" :class="tab === 'flights' && 'active'" @click="tab = 'flights'">
            <svg class="h-4.5 w-4.5" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M21 16v-2l-8-5V3.5A1.5 1.5 0 0 0 11.5 2 1.5 1.5 0 0 0 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5Z"/></svg>
            Flights
        </button>
        <button type="button" class="search-tab" :class="tab === 'hotels' && 'active'" @click="tab = 'hotels'">
            <svg class="h-4.5 w-4.5" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M4 3h16a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1h-2v-5a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v5H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm4 3v4h3V6H8Zm5 0v4h3V6h-3ZM9 17h6v3H9v-3Z"/></svg>
            Hotels
        </button>
        <button type="button" class="search-tab" :class="tab === 'cabs' && 'active'" @click="tab = 'cabs'">
            <svg class="h-4.5 w-4.5" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M5 11 6.5 6.5A2 2 0 0 1 8.4 5h7.2a2 2 0 0 1 1.9 1.5L19 11a2 2 0 0 1 2 2v4a1 1 0 0 1-1 1h-1a2 2 0 1 1-4 0H9a2 2 0 1 1-4 0H4a1 1 0 0 1-1-1v-4a2 2 0 0 1 2-2Zm2.1 0h9.8l-1-3.4a.5.5 0 0 0-.48-.35H8.58a.5.5 0 0 0-.48.35L7.1 11ZM6.5 15.5a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5Zm11 0a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5Z"/></svg>
            Cabs
        </button>
        <button type="button" class="search-tab" :class="tab === 'packages' && 'active'" @click="tab = 'packages'">
            <svg class="h-4.5 w-4.5" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="8" width="18" height="12" rx="2"/><path d="M8 8V6a4 4 0 0 1 8 0v2M3 13h18"/></svg>
            Packages
        </button>
    </div>

    {{-- FLIGHTS --}}
    <form x-cloak x-show="tab === 'flights'" action="{{ route('flights.results') }}" method="GET" class="grid gap-3"
          x-data="flightSearch({
            airports: @js($airports->map(fn ($a) => ['code' => $a->code, 'city' => $a->city, 'name' => $a->name])->values()),
            initialFrom: @js(request('from')),
            initialTo: @js(request('to')),
          })"
          @submit="if (!submitOk()) $event.preventDefault()">
        {{-- Trip type --}}
        <div class="flex flex-wrap gap-2">
            @foreach (['oneway' => 'One Way', 'round' => 'Round Trip'] as $tripValue => $tripLabel)
                <label class="cursor-pointer">
                    <input type="radio" name="trip_type_ui" value="{{ $tripValue }}" class="peer sr-only"
                           x-model="tripType">
                    <span class="inline-block rounded-full px-4 py-1.5 text-xs font-semibold text-ink-700 ring-1 ring-slate-300 transition peer-checked:bg-brand-600 peer-checked:text-white peer-checked:ring-brand-600">
                        {{ $tripLabel }}
                    </span>
                </label>
            @endforeach
        </div>

        <div class="grid items-stretch gap-3"
              :class="tripType === 'round'
                  ? 'lg:grid-cols-[1fr_auto_1fr_1fr_1fr_1.1fr_auto]'
                  : 'lg:grid-cols-[1fr_auto_1fr_1fr_1.1fr_auto]'">
            {{-- From (type-ahead) --}}
            <div class="field relative" @click.outside="showFrom = false">
                <svg class="h-5 w-5 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                <label class="min-w-0 flex-1">
                    <span class="field-label">From</span>
                    <input type="text" class="field-input" placeholder="City or airport" autocomplete="off"
                           x-model="fromQuery" @input="openFrom = fromQuery.trim().length > 0"
                           @focus="openFrom = fromQuery.trim().length > 0"
                           @keydown.arrow-down.prevent="highlightFrom = Math.min(highlightFrom + 1, fromMatches.length - 1)"
                           @keydown.arrow-up.prevent="highlightFrom = Math.max(highlightFrom - 1, 0)"
                           @keydown.enter.prevent="pickFrom(fromMatches[highlightFrom] ?? null)"
                           @keydown.escape="openFrom = false">
                    <input type="hidden" name="from" :value="fromCode">
                </label>
                <div x-cloak x-show="openFrom && fromMatches.length" class="absolute left-0 right-0 top-full z-30 mt-1 max-h-64 overflow-y-auto rounded-xl bg-white py-1 shadow-float ring-1 ring-slate-900/10">
                    <template x-for="(a, i) in fromMatches" :key="'from-' + a.code + '-' + i">
                        <button type="button" class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left text-sm hover:bg-brand-50"
                                :class="highlightFrom === i && 'bg-brand-50'"
                                @mouseenter="highlightFrom = i" @click="pickFrom(a)">
                            <span class="min-w-0">
                                <span class="block truncate font-semibold text-ink-900" x-text="a.city + ' (' + a.code + ')'"></span>
                                <span class="block truncate text-xs text-ink-500" x-text="a.name"></span>
                            </span>
                            <span class="shrink-0 rounded bg-brand-50 px-1.5 py-0.5 text-[10px] font-bold text-brand-700" x-text="a.code"></span>
                        </button>
                    </template>
                    <p x-show="!fromMatches.length" class="px-4 py-3 text-xs text-ink-500">No airports match — try a city or code.</p>
                </div>
            </div>

            {{-- Swap --}}
            <button type="button" @click="swap()" title="Swap airports"
                    class="mx-auto flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-brand-600 shadow-sm transition hover:rotate-180 hover:border-brand-400">
                <svg class="h-4.5 w-4.5" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4 4 4m6 0v12m0 0 4-4m-4 4-4-4"/></svg>
            </button>

            {{-- To (type-ahead) --}}
            <div class="field relative" @click.outside="showTo = false">
                <svg class="h-5 w-5 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                <label class="min-w-0 flex-1">
                    <span class="field-label">To</span>
                    <input type="text" class="field-input" placeholder="City or airport" autocomplete="off"
                           x-model="toQuery" @input="openTo = toQuery.trim().length > 0"
                           @focus="openTo = toQuery.trim().length > 0"
                           @keydown.arrow-down.prevent="highlightTo = Math.min(highlightTo + 1, toMatches.length - 1)"
                           @keydown.arrow-up.prevent="highlightTo = Math.max(highlightTo - 1, 0)"
                           @keydown.enter.prevent="pickTo(toMatches[highlightTo] ?? null)"
                           @keydown.escape="openTo = false">
                    <input type="hidden" name="to" :value="toCode">
                </label>
                <div x-cloak x-show="openTo && toMatches.length" class="absolute left-0 right-0 top-full z-30 mt-1 max-h-64 overflow-y-auto rounded-xl bg-white py-1 shadow-float ring-1 ring-slate-900/10">
                    <template x-for="(a, i) in toMatches" :key="'to-' + a.code + '-' + i">
                        <button type="button" class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left text-sm hover:bg-brand-50"
                                :class="highlightTo === i && 'bg-brand-50'"
                                @mouseenter="highlightTo = i" @click="pickTo(a)">
                            <span class="min-w-0">
                                <span class="block truncate font-semibold text-ink-900" x-text="a.city + ' (' + a.code + ')'"></span>
                                <span class="block truncate text-xs text-ink-500" x-text="a.name"></span>
                            </span>
                            <span class="shrink-0 rounded bg-brand-50 px-1.5 py-0.5 text-[10px] font-bold text-brand-700" x-text="a.code"></span>
                        </button>
                    </template>
                    <p x-show="!toMatches.length" class="px-4 py-3 text-xs text-ink-500">No airports match — try a city or code.</p>
                </div>
            </div>

            <div class="field">
                <svg class="h-5 w-5 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                <label class="min-w-0 flex-1">
                    <span class="field-label">Departure</span>
                    <input type="date" name="departure" class="field-input" required value="{{ request('departure', now()->addDays(7)->format('Y-m-d')) }}"
                           @change="$refs.returnMin = $event.target.value">
                </label>
            </div>
            <div class="field" x-show="tripType === 'round'" x-transition.opacity.duration.150ms>
                <svg class="h-5 w-5 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                <label class="min-w-0 flex-1">
                    <span class="field-label">Return</span>
                    <input type="date" name="return" class="field-input" x-ref="returnInput"
                           value="{{ request('return', now()->addDays(12)->format('Y-m-d')) }}"
                           :min="$refs.returnMin ? $refs.returnMin.value : ''" :required="tripType === 'round'">
                </label>
            </div>
            <div class="field">
                <svg class="h-5 w-5 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                <label class="min-w-0 flex-1">
                    <span class="field-label">Travellers &amp; Cabin</span>
                    <select name="travellers" class="field-input">
                        @foreach (['1' => '1 Adult, Economy', '2' => '2 Adults, Economy', '3' => '3 Adults, Economy', '4' => '4 Adults, Economy', '1-business' => '1 Adult, Business', '2-business' => '2 Adults, Business'] as $k => $label)
                            <option value="{{ $k }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <button type="submit" class="btn-primary btn-lg h-full whitespace-nowrap rounded-2xl px-7">
                Search Flights
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
            </button>
        </div>
    </form>

    {{-- HOTELS --}}
    <form x-cloak x-show="tab === 'hotels'" action="{{ route('hotels.search') }}" method="GET" class="grid gap-3"
          x-data="hotelOccupancy({
            adults: {{ (int) request('adults', 2) }},
            children: {{ (int) request('children', 0) }},
            infants: {{ (int) request('infants', 0) }},
            rooms: {{ (int) request('rooms', 1) }},
          })">
        <div class="grid items-stretch gap-3 lg:grid-cols-[1.3fr_1fr_1fr_1fr_auto]">
            <div class="field">
                <svg class="h-5 w-5 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                <label class="min-w-0 flex-1">
                    <span class="field-label">Destination</span>
                    <input type="text" name="destination" list="hotel-destinations" class="field-input" placeholder="Srinagar, Gulmarg…" required value="{{ request('destination') }}">
                    <datalist id="hotel-destinations">
                        @foreach ($destinations as $destination)
                            <option value="{{ $destination->name }}">{{ $destination->region }}</option>
                        @endforeach
                    </datalist>
                </label>
            </div>
            <div class="field">
                <label class="min-w-0 flex-1"><span class="field-label">Check In</span>
                    <input type="date" name="check_in" class="field-input" required value="{{ request('check_in', now()->addDays(7)->format('Y-m-d')) }}">
                </label>
            </div>
            <div class="field">
                <label class="min-w-0 flex-1"><span class="field-label">Check Out</span>
                    <input type="date" name="check_out" class="field-input" required value="{{ request('check_out', now()->addDays(10)->format('Y-m-d')) }}">
                </label>
            </div>

            {{-- Occupancy picker (chip-style, MakeMyTrip-like) --}}
            <div class="field relative !block" @click.outside="occ = false">
                <input type="hidden" name="adults" :value="adults">
                <input type="hidden" name="children" :value="children">
                <input type="hidden" name="infants" :value="infants">
                <input type="hidden" name="rooms" :value="rooms">

                <button type="button" class="flex w-full items-center gap-2 text-left" @click="occ = !occ">
                    <svg class="h-5 w-5 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                    <span class="min-w-0 flex-1">
                        <span class="field-label">Guests &amp; Rooms</span>
                        <span class="field-input block truncate" x-text="summary"></span>
                    </span>
                    <svg class="h-4 w-4 shrink-0 text-ink-400 transition" :class="occ && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                </button>

                <div x-cloak x-show="occ" x-transition
                     class="absolute right-0 top-full z-40 mt-2 w-[20rem] max-w-[calc(100vw-2rem)] rounded-2xl bg-white p-5 shadow-float ring-1 ring-slate-900/10">
                    {{-- Adults --}}
                    <p class="text-xs font-bold uppercase tracking-wide text-ink-700">Adults <span class="font-medium normal-case text-ink-400">(12+ years)</span></p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <template x-for="n in adultOptions" :key="'a' + n.value">
                            <button type="button" @click="adults = n.value; if (rooms > adults) rooms = adults"
                                    class="occ-chip" :class="adults === n.value && 'occ-chip-active'" x-text="n.label"></button>
                        </template>
                    </div>

                    {{-- Children --}}
                    <p class="mt-4 text-xs font-bold uppercase tracking-wide text-ink-700">Children <span class="font-medium normal-case text-ink-400">(2-12 years)</span></p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <template x-for="n in childOptions" :key="'c' + n.value">
                            <button type="button" @click="children = n.value"
                                    class="occ-chip" :class="children === n.value && 'occ-chip-active'" x-text="n.label"></button>
                        </template>
                    </div>

                    {{-- Infants --}}
                    <p class="mt-4 text-xs font-bold uppercase tracking-wide text-ink-700">Infants <span class="font-medium normal-case text-ink-400">(below 2 years)</span></p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <template x-for="n in childOptions" :key="'i' + n.value">
                            <button type="button" @click="infants = n.value"
                                    class="occ-chip" :class="infants === n.value && 'occ-chip-active'" x-text="n.label"></button>
                        </template>
                    </div>

                    {{-- Rooms --}}
                    <p class="mt-4 text-xs font-bold uppercase tracking-wide text-ink-700">Rooms</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <template x-for="n in roomOptions" :key="'r' + n.value">
                            <button type="button" @click="rooms = Math.min(n.value, adults)"
                                    class="occ-chip" :class="rooms === n.value && 'occ-chip-active'" x-text="n.label"></button>
                        </template>
                    </div>

                    <button type="button" class="btn-primary btn-sm mt-5 w-full" @click="occ = false">Apply</button>
                </div>
            </div>

            <button type="submit" class="btn-primary btn-lg h-full whitespace-nowrap rounded-2xl px-7">
                Search Hotels
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z"/></svg>
            </button>
        </div>
    </form>

    {{-- CABS --}}
    <form x-cloak x-show="tab === 'cabs'" action="{{ route('cabs.search') }}" method="GET" class="grid gap-3">
        <div class="grid items-stretch gap-3 lg:grid-cols-[1.2fr_1.2fr_1fr_1fr_auto]">
            <div class="field">
                <svg class="h-5 w-5 shrink-0 text-brand-600" fill="currentColor" viewBox="0 0 24 24"><path d="M5 11 6.5 6.5A2 2 0 0 1 8.4 5h7.2a2 2 0 0 1 1.9 1.5L19 11a2 2 0 0 1 2 2v4a1 1 0 0 1-1 1h-1a2 2 0 1 1-4 0H9a2 2 0 1 1-4 0H4a1 1 0 0 1-1-1v-4a2 2 0 0 1 2-2Z"/></svg>
                <label class="min-w-0 flex-1">
                    <span class="field-label">Pickup</span>
                    <input type="text" name="pickup" list="cab-locations" class="field-input" placeholder="Srinagar Airport" required>
                    <datalist id="cab-locations">
                        @foreach ($locations as $loc)
                            <option value="{{ $loc->name }}">
                        @endforeach
                    </datalist>
                </label>
            </div>
            <div class="field">
                <svg class="h-5 w-5 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                <label class="min-w-0 flex-1">
                    <span class="field-label">Drop</span>
                    <input type="text" name="drop" list="cab-locations" class="field-input" placeholder="Gulmarg" required>
                </label>
            </div>
            <div class="field">
                <label class="min-w-0 flex-1"><span class="field-label">Pickup Date</span>
                    <input type="date" name="pickup_date" class="field-input" required value="{{ request('pickup_date', now()->addDay()->format('Y-m-d')) }}">
                </label>
            </div>
            <div class="field">
                <label class="min-w-0 flex-1"><span class="field-label">Pickup Time</span>
                    <input type="time" name="pickup_time" class="field-input" required value="09:00">
                </label>
            </div>
            <button type="submit" class="btn-primary btn-lg h-full whitespace-nowrap rounded-2xl px-7">
                Search Cabs
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
            </button>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach (['one_way' => 'One Way', 'round_trip' => 'Round Trip', 'local_rental' => 'Local Rental', 'airport_transfer' => 'Airport Transfer'] as $k => $label)
                <label class="cursor-pointer">
                    <input type="radio" name="trip_type" value="{{ $k }}" class="peer sr-only" @checked($k === 'one_way')>
                    <span class="inline-block rounded-full px-4 py-1.5 text-xs font-semibold text-ink-700 ring-1 ring-slate-300 transition peer-checked:bg-brand-600 peer-checked:text-white peer-checked:ring-brand-600">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </form>

    {{-- PACKAGES --}}
    <form x-cloak x-show="tab === 'packages'" action="{{ route('packages.index') }}" method="GET" class="grid gap-3">
        <div class="grid items-stretch gap-3 lg:grid-cols-[1.3fr_1fr_1fr_auto]">
            <div class="field">
                <svg class="h-5 w-5 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                <label class="min-w-0 flex-1">
                    <span class="field-label">Destination or Package</span>
                    <input type="text" name="q" class="field-input" placeholder="Kashmir, Gulmarg, Honeymoon…" value="{{ request('q') }}">
                </label>
            </div>
            <div class="field">
                <label class="min-w-0 flex-1"><span class="field-label">Travel Date</span>
                    <input type="date" name="date" class="field-input" value="{{ request('date', now()->addDays(14)->format('Y-m-d')) }}">
                </label>
            </div>
            <div class="field">
                <svg class="h-5 w-5 shrink-0 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                <label class="min-w-0 flex-1">
                    <span class="field-label">Travellers</span>
                    <select name="travellers" class="field-input">
                        @foreach (['1' => '1 Traveller', '2' => '2 Travellers', '3' => '3 Travellers', '4' => '4 Travellers', '5' => '5+ Travellers'] as $k => $label)
                            <option value="{{ $k }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <button type="submit" class="btn-primary btn-lg h-full whitespace-nowrap rounded-2xl px-7">
                Find Packages
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
            </button>
        </div>
    </form>

    {{-- Trust row (admin-editable content, static labels per design) --}}
    <div class="trust-row">
        <span class="trust-item">
            <svg class="h-4.5 w-4.5 text-emerald-500" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5l-8-3Zm-1.2 13.5-3-3 1.4-1.4 1.6 1.6 4.1-4.1 1.4 1.4-5.5 5.5Z"/></svg>
            {{ settings('trust_best_price', 'Best Price Guarantee') }}
        </span>
        <span class="trust-item">
            <svg class="h-4.5 w-4.5 text-brand-600" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2 4.5 13.5h5.7L9 22l8.5-11.5h-5.7L13 2Z"/></svg>
            {{ settings('trust_easy_booking', 'Easy Booking') }}
        </span>
        <span class="trust-item">
            <svg class="h-4.5 w-4.5 text-brand-600" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.76 9.76 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
            {{ settings('trust_support', '24/7 Support') }}
        </span>
        <span class="trust-item">
            <svg class="h-4.5 w-4.5 text-brand-600" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5l-8-3Z"/></svg>
            {{ settings('trust_secure', 'Safe & Secure Payments') }}
        </span>
    </div>
</div>

@once
@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('searchWidget', (initial) => ({
            tab: initial,
        }));

        Alpine.data('hotelOccupancy', ({ adults, children, infants, rooms }) => ({
            occ: false,
            adults: adults || 2,
            children: children || 0,
            infants: infants || 0,
            rooms: rooms || 1,

            // Chip option lists (last chip is a ">N" cap, MakeMyTrip-style).
            adultOptions: [1,2,3,4,5,6,7,8,9].map(v => ({ value: v, label: String(v) })).concat([{ value: 10, label: '>9' }]),
            childOptions: [0,1,2,3,4,5,6].map(v => ({ value: v, label: String(v) })).concat([{ value: 7, label: '>6' }]),
            roomOptions: [1,2,3,4,5].map(v => ({ value: v, label: String(v) })).concat([{ value: 6, label: '>5' }]),

            get summary() {
                const parts = [`${this.adults} Adult${this.adults > 1 ? 's' : ''}`];
                if (this.children) parts.push(`${this.children} Child${this.children > 1 ? 'ren' : ''}`);
                if (this.infants) parts.push(`${this.infants} Infant${this.infants > 1 ? 's' : ''}`);
                return `${parts.join(', ')} · ${this.rooms} Room${this.rooms > 1 ? 's' : ''}`;
            },
        }));

        Alpine.data('flightSearch', ({ airports, initialFrom, initialTo }) => ({
            tripType: '{{ request('return') ? 'round' : 'oneway' }}',

            fromQuery: initialFrom ? initialFrom : '',
            fromCode: initialFrom ? initialFrom : '',
            openFrom: false,
            highlightFrom: 0,

            toQuery: initialTo ? initialTo : '',
            toCode: initialTo ? initialTo : '',
            openTo: false,
            highlightTo: 0,

            submitError: '',

            get fromMatches() {
                return this.filterAirports(this.fromQuery);
            },
            get toMatches() {
                return this.filterAirports(this.toQuery);
            },

            filterAirports(query) {
                const q = query.trim().toLowerCase();
                if (!q) return [];
                return airports.filter(a =>
                    a.city.toLowerCase().includes(q) ||
                    a.code.toLowerCase().includes(q) ||
                    a.name.toLowerCase().includes(q)
                ).slice(0, 8);
            },

            pickFrom(a) {
                if (!a) return;
                this.fromCode = a.code;
                this.fromQuery = a.city + ' (' + a.code + ')';
                this.openFrom = false;
                // keep From and To different
                if (this.toCode === a.code) {
                    this.toCode = '';
                    this.toQuery = '';
                }
            },

            pickTo(a) {
                if (!a) return;
                this.toCode = a.code;
                this.toQuery = a.city + ' (' + a.code + ')';
                this.openTo = false;
                if (this.fromCode === a.code) {
                    this.fromCode = '';
                    this.fromQuery = '';
                }
            },

            swap() {
                const fc = this.fromCode, fq = this.fromQuery;
                this.fromCode = this.toCode;
                this.fromQuery = this.toQuery;
                this.toCode = fc;
                this.toQuery = fq;
            },

            submitOk() {
                this.submitError = '';
                if (!this.fromCode || !this.toCode) {
                    this.submitError = 'Please pick both departure and destination airports from the suggestions.';
                    return false;
                }
                if (this.fromCode === this.toCode) {
                    this.submitError = 'Departure and destination airports must be different.';
                    return false;
                }
                return true;
            },
        }));
    });
</script>
@endpush
@endonce

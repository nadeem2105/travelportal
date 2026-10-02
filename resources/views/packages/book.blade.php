@extends('layouts.site')

@php
    $mealLabels = ['room_only' => 'Room Only', 'breakfast' => 'Breakfast Included', 'half_board' => 'Breakfast + Dinner', 'full_board' => 'All Meals'];

    // Build the hotel-selection data model for Alpine (segments + whole-trip options).
    $mapOption = function ($o) use ($mealLabels) {
        return [
            'id' => $o->id,
            'name' => $o->displayName(),
            'star' => (int) ($o->star_rating ?? $o->hotel->star_rating ?? 0),
            'city' => $o->hotel->city ?? null,
            'address' => $o->hotel->address ?? null,
            'image' => img($o->hotel->cover_image ?? null, 'images/destinations/srinagar.svg'),
            'room_type' => $o->room_type ?? optional($o->room)->room_type,
            'meal_plan' => $o->meal_plan,
            'meal_label' => $mealLabels[$o->meal_plan] ?? ucfirst(str_replace('_', ' ', $o->meal_plan)),
            'amenities' => array_slice((array) ($o->hotel->amenities ?? []), 0, 5),
            'is_default' => (bool) $o->is_default,
            'upgrade_price' => (float) $o->upgrade_price,
            'price_basis' => $o->price_basis,
            'refundable' => (bool) $o->refundable,
            'cancellation_policy' => $o->cancellation_policy,
            'max_adults' => (int) $o->max_adults,
            'max_children' => (int) $o->max_children,
        ];
    };

    $hotelSegments = [];
    foreach ($package->hotelSegments as $segment) {
        if ($segment->options->isEmpty()) {
            continue;
        }
        $hotelSegments[] = [
            'key' => (string) $segment->id,
            'label' => $segment->label,
            'city' => $segment->city,
            'nights' => (int) $segment->nights,
            'options' => $segment->options->map($mapOption)->values()->all(),
        ];
    }
    if ($package->hotelOptions->count()) {
        $hotelSegments[] = [
            'key' => 'whole',
            'label' => 'Your Stay',
            'city' => $package->destination->name ?? null,
            'nights' => (int) $package->duration_nights,
            'options' => $package->hotelOptions->map($mapOption)->values()->all(),
        ];
    }
    $offersHotels = $package->offersHotelSelection() && count($hotelSegments) > 0;

    // Flight options (MakeMyTrip-style with/without flights).
    $flightOptions = $package->flightOptions->map(fn ($f) => [
        'id' => $f->id,
        'name' => $f->displayName(),
        'airline' => $f->airline,
        'origin' => $f->origin_airport_code ?: $f->origin_city,
        'destination' => $f->destination_airport_code,
        'trip_type' => $f->trip_type,
        'trip_label' => ucfirst(str_replace('_', ' ', $f->trip_type)),
        'cabin' => ucwords(str_replace('_', ' ', $f->cabin_class)),
        'baggage' => $f->baggage,
        'price' => (float) $f->price,
        'price_basis' => $f->price_basis,
        'is_default' => (bool) $f->is_default,
        'refundable' => (bool) $f->refundable,
    ])->values()->all();
    $offersFlights = $package->offersFlightSelection() && count($flightOptions) > 0;
@endphp

@section('page')
<section class="shell max-w-6xl pt-28 pb-14">
    <nav class="text-xs text-ink-500">
        <a href="{{ route('home') }}" class="hover:text-brand-700">Home</a> ›
        <a href="{{ route('packages.index') }}" class="hover:text-brand-700">Packages</a> ›
        <a href="{{ route('packages.show', $package) }}" class="hover:text-brand-700">{{ $package->name }}</a> ›
        <span class="text-ink-900">Book</span>
    </nav>

    <h1 class="font-display mt-3 text-3xl font-bold">{{ $package->name }}</h1>
    <p class="mt-1 text-sm text-ink-500">
        @if ($package->destination) 📍 {{ $package->destination->name }} · @endif
        {{ $package->duration_days }} Days / {{ $package->duration_nights }} Nights
    </p>

    <form id="book-form" action="{{ route('packages.book', $package) }}" method="POST" novalidate
          class="mt-6 grid gap-6 lg:grid-cols-[1fr_340px]"
          x-data="bookingWizard({
            quoteUrl: '{{ route('packages.quote', $package) }}',
            offersHotels: {{ $offersHotels ? 'true' : 'false' }},
            hotelRequired: {{ $package->requiresHotelSelection() ? 'true' : 'false' }},
            segments: @js($hotelSegments),
            defaults: @js(array_map('strval', $defaultOptionIds)),
            offersFlights: {{ $offersFlights ? 'true' : 'false' }},
            flightRequired: {{ $package->requiresFlightSelection() ? 'true' : 'false' }},
            flightOptions: @js($flightOptions),
            defaultFlightId: {{ $defaultFlightId ?? 'null' }},
            maxTravellers: {{ $package->max_travellers }},
            initial: {
                adults: {{ $adults }}, children: {{ $children }}, rooms: {{ $rooms }},
                date: '{{ $departureDate }}',
                adultPrice: {{ $adultPrice }}, childPrice: {{ $childPrice }},
                total: {{ $pricing['total'] }}, tax: {{ $pricing['tax_amount'] }},
                fees: {{ $pricing['service_fee'] + $pricing['convenience_fee'] }},
                hotelUpgrade: {{ $hotelUpgradeTotal }},
                flightTotal: {{ $flightTotal ?? 0 }}
            }
          })">
        @csrf

        {{-- Step indicator --}}
        <div class="col-span-full flex items-center gap-2 sm:gap-3 mb-2 overflow-x-auto">
            <template x-for="(s, i) in steps" :key="s.key">
                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    <button type="button" @click="goto(i)" class="flex items-center gap-2 min-w-0">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold transition"
                              :class="current >= i ? 'bg-brand-600 text-white' : 'bg-slate-200 text-ink-500'" x-text="i + 1"></span>
                        <span class="hidden sm:block text-xs font-semibold transition"
                              :class="current >= i ? 'text-ink-900' : 'text-ink-500'" x-text="s.label"></span>
                    </button>
                    <span x-show="i < steps.length - 1" class="h-px w-4 sm:w-8 transition" :class="current > i ? 'bg-brand-600' : 'bg-slate-200'"></span>
                </div>
            </template>
        </div>

        {{-- LEFT: step content --}}
        <div class="space-y-5">

            {{-- STEP: Travel details --}}
            <div x-show="is('travel')" class="card p-6">
                <h2 class="font-display text-lg font-bold">Travel Details</h2>
                <p class="text-sm text-ink-500">When are you travelling and with how many people?</p>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="label">Departure Date</label>
                        <input type="date" name="departure_date" class="input" x-model="date" @change="requote"
                               min="{{ now()->addDays(2)->toDateString() }}" required>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="label">Adults (12+)</label>
                            <input type="number" name="adults" class="input" min="1" max="{{ $package->max_travellers }}"
                                   x-model.number="adults" @change="clampAndRequote" required>
                        </div>
                        <div>
                            <label class="label">Children (2-11)</label>
                            <input type="number" name="children" class="input" min="0" max="10"
                                   x-model.number="children" @change="clampAndRequote" required>
                        </div>
                        <div>
                            <label class="label">Rooms</label>
                            <input type="number" name="rooms" class="input" min="1" max="6" x-model.number="rooms" @change="requote">
                        </div>
                    </div>
                </div>

                <button type="button" class="btn-primary btn-lg mt-6 w-full" @click="next">
                    <span x-text="'Continue to ' + nextLabel()"></span>
                </button>
            </div>

            {{-- STEP: Flight selection (MakeMyTrip-style with/without flights) --}}
            <template x-if="offersFlights">
                <div x-show="is('flight')" x-cloak class="space-y-4">
                    <div class="card p-6">
                        <h2 class="font-display text-lg font-bold">Add Flights</h2>
                        <p class="text-sm text-ink-500">
                            Choose a flight for your trip@if(!$package->requiresFlightSelection()) — or continue without flights@endif.
                        </p>
                    </div>

                    <div class="card p-5">
                        <div class="grid gap-3 sm:grid-cols-2">
                            {{-- Without Flights (only when optional) --}}
                            <template x-if="!flightRequired">
                                <button type="button" @click="selectFlight(null)"
                                        class="relative text-left rounded-2xl border p-4 transition"
                                        :class="selectedFlight === null ? 'border-brand-600 ring-2 ring-brand-600/30 bg-brand-50/40' : 'border-slate-200 hover:border-brand-300'">
                                    <span x-show="selectedFlight === null" class="absolute right-3 top-3 rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold text-white">✓ Selected</span>
                                    <p class="font-bold text-ink-900">Without Flights</p>
                                    <p class="mt-1 text-xs text-ink-500">I'll arrange my own travel to the destination.</p>
                                    <p class="mt-3 border-t border-slate-100 pt-2 text-sm font-bold text-emerald-600">₹0</p>
                                </button>
                            </template>

                            <template x-for="f in flightOptions" :key="f.id">
                                <button type="button" @click="selectFlight(f.id)"
                                        class="relative text-left rounded-2xl border p-4 transition"
                                        :class="selectedFlight == f.id ? 'border-brand-600 ring-2 ring-brand-600/30 bg-brand-50/40' : 'border-slate-200 hover:border-brand-300'">
                                    <span x-show="selectedFlight == f.id" class="absolute right-3 top-3 rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold text-white">✓ Selected</span>
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg">✈</span>
                                        <p class="font-bold text-ink-900" x-text="f.name"></p>
                                        <span x-show="f.is_default" class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">Recommended</span>
                                    </div>
                                    <p class="mt-1 text-xs text-ink-500">
                                        <span x-text="f.origin || ''"></span><span x-show="f.destination" x-text="(f.trip_type === 'round_trip' ? ' ⇄ ' : ' → ') + f.destination"></span>
                                        · <span x-text="f.cabin"></span> · <span x-text="f.trip_label"></span>
                                    </p>
                                    <p class="mt-0.5 text-[11px]" :class="f.refundable ? 'text-emerald-600' : 'text-rose-500'" x-text="f.refundable ? 'Refundable' : 'Non-refundable'"></p>
                                    <p x-show="f.baggage" class="text-[11px] text-ink-500" x-text="'Baggage: ' + f.baggage"></p>
                                    <p class="mt-3 border-t border-slate-100 pt-2 text-sm font-bold text-ink-900"
                                       x-text="'+ ' + money(f.price) + (f.price_basis === 'per_person' ? ' / person' : '')"></p>
                                </button>
                            </template>
                        </div>
                    </div>

                    <template x-if="selectedFlight !== null">
                        <input type="hidden" name="flight_option_id" :value="selectedFlight">
                    </template>

                    <div class="flex gap-3">
                        <button type="button" class="btn-ghost btn-lg" @click="back">← Back</button>
                        <button type="button" class="btn-primary btn-lg flex-1" @click="next"><span x-text="'Continue to ' + nextLabel()"></span></button>
                    </div>
                </div>
            </template>

            {{-- STEP: Hotel selection --}}
            <template x-if="offersHotels">
                <div x-show="is('hotel')" class="space-y-4">
                    <div class="card p-6">
                        <h2 class="font-display text-lg font-bold">Select Your Hotel</h2>
                        <p class="text-sm text-ink-500">
                            Choose your stay@if(!$package->requiresHotelSelection()) — or keep the included hotel@endif.
                            Prices update instantly.
                        </p>
                    </div>

                    <template x-for="seg in segments" :key="seg.key">
                        <div class="card p-5">
                            <div class="flex items-center justify-between">
                                <h3 class="font-display text-base font-bold" x-text="seg.label"></h3>
                                <span class="text-xs text-ink-500" x-text="(seg.nights ? seg.nights + ' night' + (seg.nights>1?'s':'') : '') + (seg.city ? ' · ' + seg.city : '')"></span>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <template x-for="opt in seg.options" :key="opt.id">
                                    <button type="button" @click="selectHotel(seg.key, opt.id)"
                                            class="relative text-left rounded-2xl border p-4 transition"
                                            :class="selected[seg.key] == opt.id ? 'border-brand-600 ring-2 ring-brand-600/30 bg-brand-50/40' : 'border-slate-200 hover:border-brand-300'">
                                        <span x-show="selected[seg.key] == opt.id" class="absolute right-3 top-3 rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold text-white">✓ Selected</span>

                                        <div class="flex gap-3">
                                            <div class="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-slate-100">
                                                <template x-if="opt.image">
                                                    <img :src="opt.image" :alt="opt.name" class="h-full w-full object-cover">
                                                </template>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate font-bold text-ink-900" x-text="opt.name"></p>
                                                <p class="text-[11px] text-amber-500" x-text="'★'.repeat(opt.star)"></p>
                                                <p class="text-[11px] text-ink-500" x-show="opt.city" x-text="opt.city"></p>
                                            </div>
                                        </div>

                                        <div class="mt-3 space-y-1 text-xs text-ink-600">
                                            <p x-show="opt.room_type"><span class="font-semibold" x-text="opt.room_type"></span></p>
                                            <p x-text="opt.meal_label"></p>
                                            <p x-text="opt.refundable ? 'Free cancellation available' : 'Non-refundable'"
                                               :class="opt.refundable ? 'text-emerald-600' : 'text-rose-500'"></p>
                                        </div>

                                        <div class="mt-3 border-t border-slate-100 pt-2">
                                            <template x-if="opt.is_default">
                                                <span class="text-sm font-bold text-emerald-600">Included in Package</span>
                                            </template>
                                            <template x-if="!opt.is_default">
                                                <span class="text-sm font-bold text-ink-900" x-text="'+ ' + money(opt.upgrade_price)"></span>
                                            </template>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Hidden inputs for the selected option ids --}}
                    <template x-for="id in Object.values(selected)" :key="id">
                        <input type="hidden" name="hotel_option_ids[]" :value="id">
                    </template>

                    <div class="flex gap-3">
                        <button type="button" class="btn-ghost btn-lg" @click="back">← Back</button>
                        <button type="button" class="btn-primary btn-lg flex-1" @click="next"><span x-text="'Continue to ' + nextLabel()"></span></button>
                    </div>
                </div>
            </template>

            {{-- STEP: Traveller details --}}
            <div x-show="is('travellers')" x-cloak class="space-y-4">
                <div class="card p-6">
                    <h2 class="font-display text-lg font-bold">Traveller Details</h2>
                    <p class="text-sm text-ink-500">Enter details for all {{ $adults + $children }} traveller(s) as per their government ID.</p>

                    @if ($savedTravellers->count())
                        <div class="mt-3 rounded-xl bg-brand-50/60 p-3 text-xs">
                            <p class="font-semibold text-brand-700">Quick fill from saved travellers</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($savedTravellers as $saved)
                                    <button type="button"
                                            class="rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-100"
                                            @click="fillSaved(@js($saved->title ?? ''), @js($saved->first_name), @js($saved->last_name), @js(optional($saved->dob)->format('Y-m-d')), @js($saved->gender ?? ''))">
                                        + {{ $saved->full_name }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mt-5 space-y-4">
                        @for ($i = 0; $i < $adults + $children; $i++)
                            @php($isAdult = $i < $adults)
                            @php($num = $isAdult ? $i + 1 : $i - $adults + 1)
                            <div class="rounded-2xl border border-slate-200 p-4">
                                <p class="text-sm font-bold text-ink-900">
                                    <span class="mr-1.5 rounded bg-ink-900 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ strtoupper($isAdult ? 'Adult' : 'Child') }} {{ $num }}</span>
                                </p>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="label">First Name <span class="text-rose-500">*</span></label>
                                        <input type="text" name="travellers[{{ $i }}][first_name]" class="input uppercase" value="{{ old('travellers.' . $i . '.first_name') }}" required>
                                    </div>
                                    <div>
                                        <label class="label">Last Name</label>
                                        <input type="text" name="travellers[{{ $i }}][last_name]" class="input uppercase" value="{{ old('travellers.' . $i . '.last_name') }}">
                                    </div>
                                    <div>
                                        <label class="label">Date of Birth <span class="text-rose-500">*</span></label>
                                        <input type="date" name="travellers[{{ $i }}][dob]" class="input" value="{{ old('travellers.' . $i . '.dob') }}" required
                                               :max="{{ $isAdult ? '"' . now()->subYears(12)->toDateString() . '"' : 'null' }}">
                                        <p class="mt-1 text-[11px] text-ink-500">{{ $isAdult ? 'Adults must be 12+ years old' : 'Children must be under 12' }}</p>
                                    </div>
                                    <div>
                                        <label class="label">Gender <span class="text-rose-500">*</span></label>
                                        <select name="travellers[{{ $i }}][gender]" class="input" required>
                                            <option value="" disabled {{ old('travellers.' . $i . '.gender') ? '' : 'selected' }}>Select…</option>
                                            <option value="male" @selected(old('travellers.' . $i . '.gender') === 'male')>Male</option>
                                            <option value="female" @selected(old('travellers.' . $i . '.gender') === 'female')>Female</option>
                                            <option value="other" @selected(old('travellers.' . $i . '.gender') === 'other')>Other</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="label">ID Type</label>
                                        <select name="travellers[{{ $i }}][id_type]" class="input">
                                            <option value="">— Optional —</option>
                                            @foreach (['aadhaar' => 'Aadhaar', 'passport' => 'Passport', 'driving_license' => 'Driving License', 'voter_id' => 'Voter ID'] as $k => $label)
                                                <option value="{{ $k }}" @selected(old('travellers.' . $i . '.id_type') === $k)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="label">ID Number</label>
                                        <input type="text" name="travellers[{{ $i }}][id_number]" class="input" value="{{ old('travellers.' . $i . '.id_number') }}">
                                    </div>
                                </div>
                                <input type="hidden" name="travellers[{{ $i }}][type]" value="{{ $isAdult ? 'adult' : 'child' }}">
                            </div>
                        @endfor
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="button" class="btn-ghost btn-lg" @click="back">← Back</button>
                    <button type="button" class="btn-primary btn-lg flex-1" @click="next">Continue to Billing</button>
                </div>
            </div>

            {{-- STEP: Billing --}}
            <div x-show="is('billing')" x-cloak class="space-y-4">
                <div class="card p-6">
                    <h2 class="font-display text-lg font-bold">Billing Information</h2>
                    <p class="text-sm text-ink-500">Your invoice and booking confirmation will use these details.</p>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="label">Full Name (as on ID) <span class="text-rose-500">*</span></label>
                            <input type="text" name="billing_name" class="input" value="{{ old('billing_name', auth('web')->user()?->name) }}" required>
                        </div>
                        <div>
                            <label class="label">Email <span class="text-rose-500">*</span></label>
                            <input type="email" name="billing_email" class="input" value="{{ old('billing_email', auth('web')->user()?->email) }}" required>
                        </div>
                        <div>
                            <label class="label">Mobile Number <span class="text-rose-500">*</span></label>
                            <input type="tel" name="billing_phone" class="input" value="{{ old('billing_phone', auth('web')->user()?->phone) }}" placeholder="+91 XXXXX XXXXX" required>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label">Address <span class="text-rose-500">*</span></label>
                            <input type="text" name="billing_address" class="input" value="{{ old('billing_address', auth('web')->user()?->address) }}" placeholder="House no, street, locality" required>
                        </div>
                        <div>
                            <label class="label">City <span class="text-rose-500">*</span></label>
                            <input type="text" name="billing_city" class="input" value="{{ old('billing_city', auth('web')->user()?->city) }}" required>
                        </div>
                        <div>
                            <label class="label">State</label>
                            <input type="text" name="billing_state" class="input" value="{{ old('billing_state') }}">
                        </div>
                        <div>
                            <label class="label">PIN Code</label>
                            <input type="text" name="billing_pincode" class="input" value="{{ old('billing_pincode') }}" inputmode="numeric">
                        </div>
                        <div>
                            <label class="label">Country</label>
                            <input type="text" name="billing_country" class="input" value="{{ old('billing_country', 'India') }}">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label">GST Number (optional — for business invoices)</label>
                            <input type="text" name="gstin" class="input uppercase" value="{{ old('gstin') }}" maxlength="15">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label">Special Requests (optional)</label>
                            <textarea name="special_requests" rows="2" class="input" placeholder="Honeymoon decoration, wheelchair access, dietary needs…">{{ old('special_requests') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="button" class="btn-ghost btn-lg" @click="back">← Back</button>
                    <button type="button" class="btn-primary btn-lg flex-1" @click="next">Review Booking</button>
                </div>
            </div>

            {{-- STEP: Review --}}
            <div x-show="is('review')" x-cloak class="space-y-4">
                <div class="card p-6">
                    <h2 class="font-display text-lg font-bold">Review Your Booking</h2>

                    <div class="mt-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-ink-900">Package</h3>
                            <button type="button" class="text-xs font-bold text-brand-600" @click="gotoKey('travel')">Edit</button>
                        </div>
                        <dl class="mt-2 grid gap-x-8 gap-y-1.5 text-sm sm:grid-cols-2">
                            <div class="flex justify-between sm:block"><dt class="text-ink-500">Package</dt><dd class="font-bold">{{ $package->name }}</dd></div>
                            <div class="flex justify-between sm:block"><dt class="text-ink-500">Departure</dt><dd class="font-bold" x-text="fmtDate(date)"></dd></div>
                            <div class="flex justify-between sm:block"><dt class="text-ink-500">Travellers</dt><dd class="font-bold"><span x-text="adults"></span> Adults<span x-show="children"> + <span x-text="children"></span> Children</span> · <span x-text="rooms"></span> Room(s)</dd></div>
                            <div class="flex justify-between sm:block"><dt class="text-ink-500">Duration</dt><dd class="font-bold">{{ $package->duration_days }}D / {{ $package->duration_nights }}N</dd></div>
                        </dl>
                    </div>

                    <template x-if="offersHotels && selectedHotelSummary().length">
                        <div class="mt-5 border-t border-slate-100 pt-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold text-ink-900">Hotels</h3>
                                <button type="button" class="text-xs font-bold text-brand-600" @click="gotoKey('hotel')">Edit</button>
                            </div>
                            <div class="mt-2 space-y-2">
                                <template x-for="h in selectedHotelSummary()" :key="h.segKey">
                                    <div class="rounded-xl bg-slate-50 p-3 text-sm">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="font-bold text-ink-900"><span x-text="h.name"></span> <span class="text-[11px] text-amber-500" x-text="'★'.repeat(h.star)"></span></p>
                                                <p class="text-xs text-ink-500"><span x-text="h.segLabel"></span> · <span x-text="h.room_type || ''"></span> · <span x-text="h.meal_label"></span></p>
                                            </div>
                                            <span class="shrink-0 text-sm font-bold" :class="h.is_default ? 'text-emerald-600' : 'text-ink-900'"
                                                  x-text="h.is_default ? 'Included' : '+ ' + money(h.upgrade_price)"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="offersFlights">
                        <div class="mt-5 border-t border-slate-100 pt-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold text-ink-900">Flights</h3>
                                <button type="button" class="text-xs font-bold text-brand-600" @click="gotoKey('flight')">Edit</button>
                            </div>
                            <div class="mt-2 rounded-xl bg-slate-50 p-3 text-sm">
                                <template x-if="selectedFlightSummary()">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-bold text-ink-900">✈ <span x-text="selectedFlightSummary().name"></span></p>
                                            <p class="text-xs text-ink-500"><span x-text="selectedFlightSummary().cabin"></span> · <span x-text="selectedFlightSummary().trip_label"></span></p>
                                        </div>
                                        <span class="shrink-0 text-sm font-bold text-ink-900" x-text="money(flightTotal)"></span>
                                    </div>
                                </template>
                                <template x-if="!selectedFlightSummary()">
                                    <p class="text-ink-500">Without flights — own arrangements</p>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex gap-3">
                    <button type="button" class="btn-ghost btn-lg" @click="back">← Back</button>
                    <button type="button" class="btn-primary btn-lg flex-1" @click="submitForm">
                        Proceed to Payment — <span x-text="money(total)"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- RIGHT: sticky price summary --}}
        <aside class="h-fit lg:sticky lg:top-[88px]">
            <div class="card p-6">
                <div class="flex items-end gap-2">
                    <p class="font-display text-3xl font-extrabold text-ink-900" x-text="money(total)"></p>
                    <p class="pb-1 text-xs text-ink-500">total</p>
                    <span x-show="loading" class="pb-1 text-[11px] text-brand-600">updating…</span>
                </div>

                <div class="mt-5 space-y-2.5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-ink-500">Adults × <span x-text="adults"></span></span>
                        <span x-text="money(adultPrice * adults)"></span>
                    </div>
                    <div class="flex justify-between" x-show="children > 0">
                        <span class="text-ink-500">Children × <span x-text="children"></span></span>
                        <span x-text="money(childPrice * children)"></span>
                    </div>
                    <div class="flex justify-between" x-show="hotelUpgrade > 0">
                        <span class="text-ink-500">Hotel upgrade</span>
                        <span x-text="money(hotelUpgrade)"></span>
                    </div>
                    <div class="flex justify-between" x-show="flightTotal > 0">
                        <span class="text-ink-500">Flights</span>
                        <span x-text="money(flightTotal)"></span>
                    </div>
                    <div class="flex justify-between"><span class="text-ink-500">Taxes &amp; Fees</span><span x-text="money(tax + fees)"></span></div>
                    <div class="divider"></div>
                    <div class="flex justify-between text-base font-extrabold"><span>Total</span><span x-text="money(total)"></span></div>
                </div>

                <div class="divider my-4"></div>

                <ul class="space-y-2 text-xs text-ink-500">
                    <li class="flex gap-2"><span class="text-emerald-500">✓</span> Includes all taxes</li>
                    <li class="flex gap-2"><span class="text-emerald-500">✓</span> Invoice, hotel voucher &amp; itinerary emailed instantly</li>
                    <li class="flex gap-2"><span class="text-emerald-500">✓</span> Secure payment · UPI / Cards / NetBanking</li>
                </ul>
            </div>
        </aside>
    </form>
</section>
@endsection

@push('scripts')
<script>
    function bookingWizard(config) {
        return {
            quoteUrl: config.quoteUrl,
            offersHotels: config.offersHotels,
            hotelRequired: config.hotelRequired,
            segments: config.segments,
            offersFlights: config.offersFlights,
            flightRequired: config.flightRequired,
            flightOptions: config.flightOptions || [],
            maxTravellers: config.maxTravellers,
            current: 0,
            loading: false,
            // occupancy + date
            adults: config.initial.adults,
            children: config.initial.children,
            rooms: config.initial.rooms,
            date: config.initial.date,
            // pricing
            adultPrice: config.initial.adultPrice,
            childPrice: config.initial.childPrice,
            total: config.initial.total,
            tax: config.initial.tax,
            fees: config.initial.fees,
            hotelUpgrade: config.initial.hotelUpgrade,
            flightTotal: config.initial.flightTotal || 0,
            // hotel selection map: segKey -> optionId
            selected: {},
            // flight selection: option id or null (= without flights)
            selectedFlight: null,
            _timer: null,

            init() {
                // Preselect defaults per segment.
                for (const seg of this.segments) {
                    const def = seg.options.find(o => o.is_default) || seg.options[0];
                    if (def) this.selected[seg.key] = def.id;
                }
                // Preselect recommended flight if package requires flights.
                if (this.flightRequired) {
                    this.selectedFlight = config.defaultFlightId
                        || (this.flightOptions[0] ? this.flightOptions[0].id : null);
                }
            },

            get steps() {
                const s = [{ key: 'travel', label: 'Travel Details' }];
                if (this.offersFlights) s.push({ key: 'flight', label: 'Flights' });
                if (this.offersHotels) s.push({ key: 'hotel', label: 'Select Hotel' });
                s.push({ key: 'travellers', label: 'Travellers' });
                s.push({ key: 'billing', label: 'Billing' });
                s.push({ key: 'review', label: 'Review' });
                return s;
            },
            nextLabel() { return this.steps[this.current + 1]?.label || 'Continue'; },
            is(key) { return this.steps[this.current]?.key === key; },
            goto(i) { if (i <= this.current) { this.current = i; scroll(); } },
            gotoKey(key) { const i = this.steps.findIndex(s => s.key === key); if (i >= 0) { this.current = i; scroll(); } },

            validateCurrent() {
                const key = this.steps[this.current].key;
                const container = document.querySelector(`[x-show="is('${key}')"]`);
                if (container) {
                    for (const el of container.querySelectorAll('input, select, textarea')) {
                        if (el.offsetParent !== null && !el.checkValidity()) { el.reportValidity(); return false; }
                    }
                }
                if (key === 'hotel' && this.hotelRequired) {
                    for (const seg of this.segments) {
                        if (!this.selected[seg.key]) { alert('Please select a hotel for ' + seg.label + '.'); return false; }
                    }
                }
                if (key === 'flight' && this.flightRequired && !this.selectedFlight) {
                    alert('Please select a flight to continue.'); return false;
                }
                return true;
            },
            next() { if (!this.validateCurrent()) return; this.current = Math.min(this.steps.length - 1, this.current + 1); scroll(); },
            back() { this.current = Math.max(0, this.current - 1); scroll(); },

            clampAndRequote() {
                if (this.adults < 1) this.adults = 1;
                if ((this.adults + this.children) > this.maxTravellers) {
                    this.children = Math.max(0, this.maxTravellers - this.adults);
                }
                this.requote();
            },
            selectHotel(segKey, optId) { this.selected[segKey] = optId; this.requote(); },
            selectFlight(id) { this.selectedFlight = id; this.requote(); },

            requote() {
                clearTimeout(this._timer);
                this._timer = setTimeout(() => this.fetchQuote(), 250);
            },
            async fetchQuote() {
                if (!this.date || !this.adults) return;
                this.loading = true;
                try {
                    const res = await fetch(this.quoteUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            departure_date: this.date,
                            adults: this.adults,
                            children: this.children,
                            rooms: this.rooms,
                            hotel_option_ids: Object.values(this.selected),
                            flight_option_id: this.selectedFlight,
                        }),
                    });
                    const data = await res.json();
                    if (data.ok) {
                        this.adultPrice = data.adult_price;
                        this.childPrice = data.child_price;
                        this.hotelUpgrade = data.hotel_upgrade;
                        this.flightTotal = data.flight_total || 0;
                        this.tax = data.tax_amount;
                        this.fees = data.service_fee;
                        this.total = data.total;
                    }
                } catch (e) { /* keep last known price */ }
                this.loading = false;
            },

            selectedFlightSummary() {
                if (this.selectedFlight === null) return null;
                return this.flightOptions.find(f => f.id == this.selectedFlight) || null;
            },

            selectedHotelSummary() {
                const out = [];
                for (const seg of this.segments) {
                    const id = this.selected[seg.key];
                    const opt = seg.options.find(o => o.id == id);
                    if (opt) out.push({ segKey: seg.key, segLabel: seg.label, ...opt });
                }
                return out;
            },

            fillSaved(title, firstName, lastName, dob, gender) {
                const form = document.getElementById('book-form');
                for (let i = 0; i < {{ $adults + $children }}; i++) {
                    const first = form.querySelector(`[name='travellers[${i}][first_name]']`);
                    if (first && first.value.trim() === '') {
                        first.value = firstName;
                        const last = form.querySelector(`[name='travellers[${i}][last_name]']`); if (last && lastName) last.value = lastName;
                        const dobEl = form.querySelector(`[name='travellers[${i}][dob]']`); if (dobEl && dob) dobEl.value = dob;
                        const gen = form.querySelector(`[name='travellers[${i}][gender]']`); if (gen && gender) gen.value = gender;
                        return;
                    }
                }
                alert('All traveller rows are already filled.');
            },

            submitForm() { if (this.validateCurrent()) document.getElementById('book-form').submit(); },

            money(v) { return '₹' + Number(v || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 }); },
            fmtDate(d) { try { return new Date(d).toLocaleDateString('en-IN', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' }); } catch (e) { return d; } },
        };
    }
    function scroll() { window.scrollTo({ top: 0, behavior: 'smooth' }); }
</script>
@endpush

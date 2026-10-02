@extends('layouts.admin')
@section('pageTitle', ($package->exists ? 'Edit: ' . $package->name : 'New Package'))

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="font-display text-xl font-bold">{{ $package->exists ? 'Edit: ' . $package->name : 'New Package' }}</h1>
        @if ($package->exists)
            <div class="flex gap-2">
                @if (auth('admin')->user()?->can('marketing.assets'))
                    <a href="{{ route('admin.studio.create', ['product_type' => 'package', 'product_id' => $package->id]) }}" class="btn-primary btn-sm">✨ Create Ad</a>
                @endif
                <a href="{{ route('packages.show', $package) }}" target="_blank" class="btn-ghost btn-sm">View on site ↗</a>
            </div>
        @endif
    </div>

    <form action="{{ $package->exists ? route('admin.packages.update', $package) : route('admin.packages.store') }}"
          method="POST" class="admin-card mt-4">
        @csrf
        @if ($package->exists) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Package Name</label><input type="text" name="name" class="input" value="{{ old('name', $package->name) }}" required></div>
            <div>
                <label class="label">Destination</label>
                <select name="destination_id" class="input">
                    <option value="">— None —</option>
                    @foreach ($destinations as $destination)
                        <option value="{{ $destination->id }}" @selected(old('destination_id', $package->destination_id) == $destination->id)>{{ $destination->name }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="label">Duration Days</label><input type="number" name="duration_days" class="input" value="{{ old('duration_days', $package->duration_days ?? 5) }}" required></div>
            <div><label class="label">Duration Nights</label><input type="number" name="duration_nights" class="input" value="{{ old('duration_nights', $package->duration_nights ?? 4) }}" required></div>
            <div><label class="label">Base Price / person (₹)</label><input type="number" step="any" name="base_price" class="input" value="{{ old('base_price', $package->base_price) }}" required></div>
            <div><label class="label">Child Price (₹)</label><input type="number" step="any" name="child_price" class="input" value="{{ old('child_price', $package->child_price) }}"></div>
            <div><label class="label">Discount %</label><input type="number" step="any" name="discount_percent" class="input" value="{{ old('discount_percent', $package->discount_percent ?? 0) }}"></div>
            <div>
                <label class="label">Package Type</label>
                <select name="package_type" class="input">
                    @foreach (['group' => 'Group', 'private' => 'Private', 'custom' => 'Custom'] as $k => $label)
                        <option value="{{ $k }}" @selected(old('package_type', $package->package_type ?? 'group') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="label">Max Travellers</label><input type="number" name="max_travellers" class="input" value="{{ old('max_travellers', $package->max_travellers ?? 20) }}"></div>
            <div>
                <label class="label">Hotel Selection</label>
                <select name="hotel_mode" class="input">
                    @foreach (['none' => 'No hotel step (land-only)', 'optional' => 'Optional — customer may skip', 'required' => 'Required — must pick a hotel'] as $k => $label)
                        <option value="{{ $k }}" @selected(old('hotel_mode', $package->hotel_mode ?? 'none') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-ink-500">Controls the hotel-selection step. Configure hotels in the Hotels panel below.</p>
            </div>
            <div>
                <label class="label">Flight Selection</label>
                <select name="flight_mode" class="input">
                    @foreach (['none' => 'No flights (land-only)', 'optional' => 'Optional — with / without flights', 'required' => 'Required — fly-in package'] as $k => $label)
                        <option value="{{ $k }}" @selected(old('flight_mode', $package->flight_mode ?? 'none') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-ink-500">MakeMyTrip-style. Configure fares in the Flights panel below.</p>
            </div>
            <div>
                <label class="label">Status</label>
                <select name="status" class="input">
                    @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'draft' => 'Draft'] as $k => $label)
                        <option value="{{ $k }}" @selected(old('status', $package->status ?? 'active') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2"><label class="label">Short Description</label><input type="text" name="short_description" class="input" value="{{ old('short_description', $package->short_description) }}"></div>
            <div class="sm:col-span-2"><label class="label">Full Description</label><textarea name="description" rows="5" class="input">{{ old('description', $package->description) }}</textarea></div>
            <div><label class="label">Cover Image</label><x-admin.image-upload name="cover_image" :value="old('cover_image', $package->cover_image)" folder="packages" /></div>
            <div><label class="label">Gallery</label><x-admin.multi-image-upload name="gallery" :values="old('gallery', $package->gallery ?? [])" folder="packages" /></div>
            <div><label class="label">Highlights (one per line)</label><textarea name="highlights" rows="4" class="input">{{ old('highlights', is_array($package->highlights) ? implode("\n", $package->highlights) : '') }}</textarea></div>
            <div><label class="label">Inclusions (one per line)</label><textarea name="inclusions" rows="4" class="input">{{ old('inclusions', is_array($package->inclusions) ? implode("\n", $package->inclusions) : "Hotel\nSightseeing\nCab") }}</textarea></div>
            <div><label class="label">Exclusions (one per line)</label><textarea name="exclusions" rows="3" class="input">{{ old('exclusions', is_array($package->exclusions) ? implode("\n", $package->exclusions) : '') }}</textarea></div>
            <div><label class="label">Cancellation Policy</label><textarea name="cancellation_policy" rows="3" class="input">{{ old('cancellation_policy', $package->cancellation_policy) }}</textarea></div>
            <label class="flex items-end gap-2 pb-2 text-sm font-semibold">
                <input type="checkbox" name="is_featured" value="1" class="accent-brand-600" @checked(old('is_featured', $package->is_featured ?? false))> Featured on homepage
            </label>
        </div>

        <div class="mt-5 flex gap-2">
            <button class="btn-primary btn-md">{{ $package->exists ? 'Save Changes' : 'Create Package' }}</button>
            <a href="{{ route('admin.packages.index') }}" class="btn-ghost btn-md">Back</a>
        </div>
    </form>

    @unless ($package->exists)
        <div class="alert-info mt-6">
            <p class="font-semibold">Next step: itinerary, seasonal pricing &amp; departures</p>
            <p class="mt-1 text-xs leading-5">
                Fill the required fields above and click <strong>Create Package</strong> — the
                <strong>Itinerary builder</strong> (day-wise plan), <strong>Seasonal Pricing</strong> and
                <strong>Departures &amp; Inventory</strong> panels will appear on this page right after saving,
                ready for you to fill in.
            </p>
        </div>
    @endunless

    @if ($package->exists)
        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            {{-- Itinerary --}}
            <div class="admin-card">
                <h2 class="font-display text-base font-bold">Itinerary ({{ $package->itineraries->count() }} days)</h2>
                <div class="mt-2 max-h-96 space-y-2 overflow-y-auto pr-1 text-sm">
                    @forelse ($package->itineraries as $day)
                        <details class="rounded-xl border border-slate-200" @if($loop->first && $package->itineraries->count() <= 3) open @endif>
                            <summary class="flex cursor-pointer list-none items-center gap-2 p-2.5">
                                <span class="rounded bg-brand-600 px-1.5 py-0.5 text-[10px] font-bold text-white">D{{ $day->day_number }}</span>
                                <span class="min-w-0 flex-1 truncate font-semibold text-ink-900">{{ $day->title }}</span>
                                <span class="text-[10px] font-bold text-brand-600">Edit</span>
                            </summary>
                            <div class="border-t border-slate-100 p-3">
                                <form action="{{ route('admin.packages.itineraries.update', [$package, $day]) }}" method="POST" class="space-y-2">
                                    @csrf
                                    @method('PUT')
                                    <div class="grid grid-cols-[70px_1fr] gap-2">
                                        <input type="number" name="day_number" class="input" placeholder="Day" min="1" value="{{ $day->day_number }}" required>
                                        <input type="text" name="title" class="input" value="{{ $day->title }}" required>
                                    </div>
                                    <textarea name="description" rows="3" class="input" required>{{ $day->description }}</textarea>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="text" name="meals" class="input" placeholder="Meals (comma sep)" value="{{ is_array($day->meals) ? implode(', ', $day->meals) : '' }}">
                                        <input type="text" name="overnight_stay" class="input" placeholder="Overnight stay" value="{{ $day->overnight_stay }}">
                                    </div>
                                    <div class="flex items-center justify-between gap-2 pt-1">
                                        <button class="btn-primary btn-sm">Save Day</button>
                                    </div>
                                </form>
                                <form action="{{ route('admin.packages.itineraries.destroy', [$package, $day]) }}" method="POST" class="mt-2 border-t border-slate-100 pt-2"
                                      onclick="return confirm('Delete Day {{ $day->day_number }} — {{ \Illuminate\Support\Str::limit($day->title, 40) }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-xs font-bold text-rose-500 hover:text-rose-700">Delete this day</button>
                                </form>
                            </div>
                        </details>
                    @empty
                        <p class="text-ink-500">No days added yet — use the form below.</p>
                    @endforelse
                </div>
                <form action="{{ route('admin.packages.itineraries', $package) }}" method="POST" class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                    @csrf
                    <div class="grid grid-cols-[70px_1fr] gap-2">
                        <input type="number" name="day_number" class="input" placeholder="Day" min="1" value="{{ $package->itineraries->max('day_number') + 1 }}" required>
                        <input type="text" name="title" class="input" placeholder="Day title" required>
                    </div>
                    <textarea name="description" rows="2" class="input" placeholder="Description" required></textarea>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="meals" class="input" placeholder="Meals (comma sep)">
                        <input type="text" name="overnight_stay" class="input" placeholder="Overnight stay">
                    </div>
                    <button class="btn-ghost btn-sm w-full">Add Day</button>
                </form>
            </div>

            {{-- Seasonal pricing --}}
            <div class="admin-card">
                <h2 class="font-display text-base font-bold">Seasonal Pricing</h2>
                <div class="mt-2 max-h-56 space-y-1.5 overflow-y-auto text-sm">
                    @forelse ($package->seasonalPrices as $price)
                        <div class="flex justify-between rounded-lg bg-slate-50 px-2.5 py-1.5">
                            <span class="font-semibold capitalize">{{ $price->season }} {{ $price->label ? '· ' . $price->label : '' }}</span>
                            <span>{{ money($price->price_per_person) }}</span>
                        </div>
                    @empty
                        <p class="text-ink-500">Uses base price</p>
                    @endforelse
                </div>
                <form action="{{ route('admin.packages.prices', $package) }}" method="POST" class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="season" class="input" placeholder="Season (peak/winter)" required>
                        <input type="number" step="any" name="price_per_person" class="input" placeholder="₹ / person" required>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="date" name="starts_at" class="input">
                        <input type="date" name="ends_at" class="input">
                    </div>
                    <button class="btn-ghost btn-sm w-full">Add Seasonal Price</button>
                </form>
            </div>

            {{-- Departures --}}
            <div class="admin-card">
                <h2 class="font-display text-base font-bold">Departures & Inventory</h2>
                <div class="mt-2 max-h-56 space-y-1.5 overflow-y-auto text-sm">
                    @forelse ($package->departures as $departure)
                        <div class="flex justify-between rounded-lg bg-slate-50 px-2.5 py-1.5">
                            <span class="font-semibold">{{ $departure->departure_date->format('d M Y') }}</span>
                            <span>{{ $departure->seatsLeft() }}/{{ $departure->inventory }} left</span>
                        </div>
                    @empty
                        <p class="text-ink-500">Flexible dates (no fixed departures)</p>
                    @endforelse
                </div>
                <form action="{{ route('admin.packages.departures', $package) }}" method="POST" class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-2">
                        <input type="date" name="departure_date" class="input" required>
                        <input type="number" name="inventory" class="input" placeholder="Seats" min="1" required>
                    </div>
                    <input type="number" step="any" name="price_override" class="input" placeholder="Price override (optional)">
                    <button class="btn-ghost btn-sm w-full">Add Departure</button>
                </form>
            </div>
        </div>

        {{-- ============================ HOTELS ============================ --}}
        @php($mealPlans = ['room_only' => 'Room Only', 'breakfast' => 'Breakfast', 'half_board' => 'Half Board (B+D)', 'full_board' => 'Full Board'])
        <div class="admin-card mt-4"
             x-data="packageHotels(@js($hotels->map(fn ($h) => ['id' => $h->id, 'name' => $h->name, 'star' => $h->star_rating, 'city' => $h->city, 'rooms' => $h->rooms->map(fn ($r) => ['id' => $r->id, 'room_type' => $r->room_type, 'meal_plan' => $r->meal_plan])->values()])->values()))">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-base font-bold">Hotels & Room Options</h2>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $package->hotel_mode === 'none' ? 'bg-slate-100 text-ink-500' : 'bg-emerald-50 text-emerald-700' }}">
                    Mode: {{ ['none' => 'Off', 'optional' => 'Optional', 'required' => 'Required'][$package->hotel_mode] ?? 'Off' }}
                </span>
            </div>
            <p class="mt-1 text-xs text-ink-500">
                Group hotels into <strong>segments</strong> (legs of the stay, e.g. “Srinagar Day 1-2”, “Gulmarg Day 3”).
                Under each segment add hotel options — mark one as <strong>Included</strong> (₹0) and add upgrades with a price delta.
                Leave the segment blank on an option to offer it for the whole trip.
            </p>

            {{-- Segments --}}
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div>
                    <h3 class="text-sm font-bold text-ink-900">Segments</h3>
                    <div class="mt-2 space-y-2">
                        @forelse ($package->hotelSegments as $segment)
                            <details class="rounded-xl border border-slate-200">
                                <summary class="flex cursor-pointer list-none items-center gap-2 p-2.5 text-sm">
                                    <span class="rounded bg-brand-600 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $segment->nights }}N</span>
                                    <span class="min-w-0 flex-1 truncate font-semibold">{{ $segment->label }}</span>
                                    <span class="text-[10px] font-bold text-brand-600">Edit</span>
                                </summary>
                                <div class="border-t border-slate-100 p-3">
                                    <form action="{{ route('admin.packages.hotel-segments.update', [$package, $segment]) }}" method="POST" class="space-y-2">
                                        @csrf @method('PUT')
                                        <input type="text" name="label" class="input" value="{{ $segment->label }}" required>
                                        <div class="grid grid-cols-4 gap-2">
                                            <input type="text" name="city" class="input" placeholder="City" value="{{ $segment->city }}">
                                            <input type="number" name="day_from" class="input" placeholder="Day from" value="{{ $segment->day_from }}" min="1">
                                            <input type="number" name="day_to" class="input" placeholder="Day to" value="{{ $segment->day_to }}" min="1">
                                            <input type="number" name="nights" class="input" placeholder="Nights" value="{{ $segment->nights }}" min="1" required>
                                        </div>
                                        <div class="flex gap-2">
                                            <button class="btn-primary btn-sm">Save</button>
                                        </div>
                                    </form>
                                    <form action="{{ route('admin.packages.hotel-segments.destroy', [$package, $segment]) }}" method="POST" class="mt-2 border-t border-slate-100 pt-2"
                                          onsubmit="return confirm('Delete segment “{{ $segment->label }}” and its hotel options?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs font-bold text-rose-500 hover:text-rose-700">Delete segment</button>
                                    </form>
                                </div>
                            </details>
                        @empty
                            <p class="text-xs text-ink-500">No segments yet. Add one to assign a per-destination hotel, or skip and add whole-trip options on the right.</p>
                        @endforelse
                    </div>
                    <form action="{{ route('admin.packages.hotel-segments.store', $package) }}" method="POST" class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                        @csrf
                        <input type="text" name="label" class="input" placeholder="Segment label (e.g. Srinagar Day 1-2)" required>
                        <div class="grid grid-cols-4 gap-2">
                            <input type="text" name="city" class="input" placeholder="City">
                            <input type="number" name="day_from" class="input" placeholder="Day from" min="1">
                            <input type="number" name="day_to" class="input" placeholder="Day to" min="1">
                            <input type="number" name="nights" class="input" placeholder="Nights" min="1" value="1" required>
                        </div>
                        <button class="btn-ghost btn-sm w-full">Add Segment</button>
                    </form>
                </div>

                {{-- Options --}}
                <div>
                    <h3 class="text-sm font-bold text-ink-900">Hotel Options ({{ $package->hotelOptions->count() }})</h3>
                    <div class="mt-2 max-h-80 space-y-2 overflow-y-auto pr-1">
                        @forelse ($package->hotelOptions as $option)
                            <div class="rounded-xl border {{ $option->is_default ? 'border-emerald-300 bg-emerald-50/40' : 'border-slate-200' }} p-3 text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="min-w-0 flex-1 truncate font-bold text-ink-900">
                                        {{ $option->displayName() }}
                                        @if ($option->is_default)<span class="ml-1 rounded bg-emerald-600 px-1.5 py-0.5 text-[10px] font-bold text-white">Included</span>
                                        @else<span class="ml-1 rounded bg-amber-500 px-1.5 py-0.5 text-[10px] font-bold text-white">+{{ money($option->upgrade_price) }}</span>@endif
                                        @if ($option->status !== 'active')<span class="ml-1 rounded bg-slate-400 px-1.5 py-0.5 text-[10px] font-bold text-white">Inactive</span>@endif
                                    </p>
                                    <form action="{{ route('admin.packages.hotel-options.destroy', [$package, $option]) }}" method="POST" onsubmit="return confirm('Remove this hotel option?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs font-bold text-rose-500 hover:text-rose-700">✕</button>
                                    </form>
                                </div>
                                <p class="mt-0.5 text-[11px] text-ink-500">
                                    {{ $option->segment?->label ?? 'Whole trip' }} · {{ $option->room_type ?? '—' }} ·
                                    {{ $mealPlans[$option->meal_plan] ?? $option->meal_plan }} ·
                                    {{ $option->base_adults }} base ad · {{ $option->refundable ? 'Refundable' : 'Non-refundable' }}
                                </p>
                            </div>
                        @empty
                            <p class="text-xs text-ink-500">No hotel options yet.</p>
                        @endforelse
                    </div>

                    {{-- Add option --}}
                    <form action="{{ route('admin.packages.hotel-options.store', $package) }}" method="POST" class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                        @csrf
                        <div class="grid grid-cols-2 gap-2">
                            <select name="segment_id" class="input">
                                <option value="">Whole trip</option>
                                @foreach ($package->hotelSegments as $segment)
                                    <option value="{{ $segment->id }}">{{ $segment->label }}</option>
                                @endforeach
                            </select>
                            <select name="hotel_id" class="input" x-model="form.hotel_id" @change="onHotelChange()">
                                <option value="">— Select hotel —</option>
                                <template x-for="h in hotels" :key="h.id">
                                    <option :value="h.id" x-text="h.name + ' (' + h.star + '★)'"></option>
                                </template>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <select name="hotel_room_id" class="input" x-model="form.hotel_room_id" @change="onRoomChange()">
                                <option value="">— Room —</option>
                                <template x-for="r in rooms" :key="r.id">
                                    <option :value="r.id" x-text="r.room_type"></option>
                                </template>
                            </select>
                            <input type="text" name="room_type" class="input" placeholder="Room type label" x-model="form.room_type">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <select name="meal_plan" class="input" x-model="form.meal_plan">
                                @foreach ($mealPlans as $k => $label)
                                    <option value="{{ $k }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <select name="price_basis" class="input">
                                @foreach (['per_booking' => 'Per booking', 'per_person' => 'Per person', 'per_room' => 'Per room'] as $k => $label)
                                    <option value="{{ $k }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="label text-[11px]">Base adults</label>
                                <input type="number" name="base_adults" class="input" value="2" min="1" required>
                            </div>
                            <div>
                                <label class="label text-[11px]">Max adults</label>
                                <input type="number" name="max_adults" class="input" value="3" min="1" required>
                            </div>
                            <div>
                                <label class="label text-[11px]">Max children</label>
                                <input type="number" name="max_children" class="input" value="2" min="0" required>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="label text-[11px]">Upgrade price (₹, delta)</label>
                                <input type="number" step="any" name="upgrade_price" class="input" value="0" min="0" required :disabled="form.is_default" :class="form.is_default ? 'opacity-50' : ''">
                            </div>
                            <div>
                                <label class="label text-[11px]">Star rating</label>
                                <input type="number" name="star_rating" class="input" min="1" max="5" x-model="form.star_rating">
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="label text-[11px]">Extra adult ₹</label>
                                <input type="number" step="any" name="extra_adult_price" class="input" min="0">
                            </div>
                            <div>
                                <label class="label text-[11px]">Extra child ₹</label>
                                <input type="number" step="any" name="extra_child_price" class="input" min="0">
                            </div>
                            <div>
                                <label class="label text-[11px]">Extra bed ₹</label>
                                <input type="number" step="any" name="extra_bed_price" class="input" min="0">
                            </div>
                        </div>
                        <textarea name="cancellation_policy" rows="2" class="input" placeholder="Cancellation policy (captured on every booking of this option)"></textarea>
                        <div class="flex flex-wrap items-center gap-4 text-sm">
                            <label class="flex items-center gap-1.5 font-semibold">
                                <input type="checkbox" name="is_default" value="1" class="accent-emerald-600" x-model="form.is_default"> Included (₹0)
                            </label>
                            <label class="flex items-center gap-1.5 font-semibold">
                                <input type="checkbox" name="extra_bed_allowed" value="1" class="accent-brand-600"> Extra bed
                            </label>
                            <label class="flex items-center gap-1.5 font-semibold">
                                <input type="checkbox" name="refundable" value="1" class="accent-brand-600" checked> Refundable
                            </label>
                            <select name="status" class="input !w-auto">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <input type="hidden" name="supplier_id" value="">
                        <button class="btn-primary btn-sm w-full">Add Hotel Option</button>
                        <p class="text-[11px] text-ink-500">Manual hotels only for now. API-supplied options plug in via the same table later (supplier fields).</p>
                    </form>
                </div>
            </div>
        </div>

        {{-- ============================ FLIGHTS ============================ --}}
        <div class="admin-card mt-4">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-base font-bold">Flight Options</h2>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $package->flight_mode === 'none' ? 'bg-slate-100 text-ink-500' : 'bg-emerald-50 text-emerald-700' }}">
                    Mode: {{ ['none' => 'Off', 'optional' => 'Optional', 'required' => 'Required'][$package->flight_mode] ?? 'Off' }}
                </span>
            </div>
            <p class="mt-1 text-xs text-ink-500">
                MakeMyTrip-style “with / without flights”. Add a fare per departure city — mark one as the
                recommended default. Customers on an <strong>Optional</strong> package can also choose <strong>Without Flights</strong> (₹0).
            </p>

            <div class="mt-3 max-h-72 space-y-2 overflow-y-auto pr-1">
                @forelse ($package->flightOptions as $flight)
                    <div class="flex items-center justify-between rounded-xl border {{ $flight->is_default ? 'border-emerald-300 bg-emerald-50/40' : 'border-slate-200' }} p-3 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-bold text-ink-900">
                                {{ $flight->displayName() }}
                                @if ($flight->is_default)<span class="ml-1 rounded bg-emerald-600 px-1.5 py-0.5 text-[10px] font-bold text-white">Recommended</span>@endif
                                @if ($flight->status !== 'active')<span class="ml-1 rounded bg-slate-400 px-1.5 py-0.5 text-[10px] font-bold text-white">Inactive</span>@endif
                            </p>
                            <p class="text-[11px] text-ink-500">
                                {{ $flight->origin_airport_code ?: $flight->origin_city }}@if($flight->destination_airport_code) → {{ $flight->destination_airport_code }}@endif ·
                                {{ ucfirst(str_replace('_',' ',$flight->trip_type)) }} · {{ ucwords(str_replace('_',' ',$flight->cabin_class)) }} ·
                                {{ $flight->refundable ? 'Refundable' : 'Non-refundable' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="font-bold text-ink-900">+{{ money($flight->price) }}<span class="text-[10px] font-normal text-ink-500">/{{ $flight->price_basis === 'per_person' ? 'pax' : 'booking' }}</span></span>
                            <form action="{{ route('admin.packages.flight-options.destroy', [$package, $flight]) }}" method="POST" onsubmit="return confirm('Remove this flight option?')">
                                @csrf @method('DELETE')
                                <button class="text-xs font-bold text-rose-500 hover:text-rose-700">✕</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-ink-500">No flight options yet.</p>
                @endforelse
            </div>

            <form action="{{ route('admin.packages.flight-options.store', $package) }}" method="POST" class="mt-3 grid gap-2 border-t border-slate-100 pt-3 sm:grid-cols-2">
                @csrf
                <input type="text" name="label" class="input sm:col-span-2" placeholder="Label (e.g. Ex-Delhi Round Trip)">
                <input type="text" name="origin_city" class="input" placeholder="Origin city (Delhi)">
                <input type="text" name="origin_airport_code" class="input" placeholder="Origin code (DEL)" maxlength="8">
                <input type="text" name="destination_airport_code" class="input" placeholder="Dest code (SXR)" maxlength="8">
                <input type="text" name="airline" class="input" placeholder="Airline (optional)">
                <select name="trip_type" class="input">
                    <option value="round_trip">Round trip</option>
                    <option value="one_way">One way</option>
                </select>
                <select name="cabin_class" class="input">
                    <option value="economy">Economy</option>
                    <option value="premium_economy">Premium Economy</option>
                    <option value="business">Business</option>
                </select>
                <input type="text" name="baggage" class="input" placeholder="Baggage (15kg + 7kg)">
                <select name="price_basis" class="input">
                    <option value="per_person">Per person</option>
                    <option value="per_booking">Per booking</option>
                </select>
                <input type="number" step="any" name="price" class="input" placeholder="Fare (₹)" min="0" required>
                <textarea name="cancellation_policy" rows="2" class="input sm:col-span-2" placeholder="Cancellation policy (captured on every booking)"></textarea>
                <div class="flex flex-wrap items-center gap-4 text-sm sm:col-span-2">
                    <label class="flex items-center gap-1.5 font-semibold"><input type="checkbox" name="is_default" value="1" class="accent-emerald-600"> Recommended</label>
                    <label class="flex items-center gap-1.5 font-semibold"><input type="checkbox" name="refundable" value="1" class="accent-brand-600"> Refundable</label>
                    <select name="status" class="input !w-auto">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <input type="hidden" name="supplier_id" value="">
                    <button class="btn-primary btn-sm ml-auto">Add Flight Option</button>
                </div>
            </form>
        </div>
    @endif
@endsection

@push('scripts')
<script>
    function packageHotels(hotels) {
        return {
            hotels,
            rooms: [],
            form: { hotel_id: '', hotel_room_id: '', room_type: '', meal_plan: 'room_only', star_rating: '', is_default: false },
            onHotelChange() {
                const h = this.hotels.find(x => String(x.id) === String(this.form.hotel_id));
                this.rooms = h ? h.rooms : [];
                this.form.hotel_room_id = '';
                if (h && h.star) this.form.star_rating = h.star;
            },
            onRoomChange() {
                const r = this.rooms.find(x => String(x.id) === String(this.form.hotel_room_id));
                if (r) {
                    this.form.room_type = r.room_type;
                    if (r.meal_plan) this.form.meal_plan = r.meal_plan;
                }
            },
        };
    }
</script>
@endpush

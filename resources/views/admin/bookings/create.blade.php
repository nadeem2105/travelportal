@extends('layouts.admin')
@section('pageTitle', 'Add Booking')

@section('content')
<div class="mx-auto max-w-3xl" x-data="{ type: '{{ old('product_type', 'package') }}' }">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="font-display text-xl font-bold">Add Booking</h1>
            <p class="text-xs text-ink-500">Record an offline / phone booking directly. Amounts you enter here are trusted.</p>
        </div>
        <a href="{{ route('admin.bookings.index') }}" class="btn-ghost btn-sm">Cancel</a>
    </div>

    @if ($errors->any())<div class="alert-error mt-3">{{ $errors->first() }}</div>@endif
    @if (session('error'))<div class="alert-error mt-3">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('admin.bookings.store') }}" class="mt-4 space-y-4">
        @csrf

        {{-- Product type --}}
        <div class="admin-card">
            <label class="label">Booking Type *</label>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach (['package' => 'Package', 'hotel' => 'Hotel', 'cab' => 'Cab', 'flight' => 'Flight'] as $val => $lbl)
                    <label class="cursor-pointer rounded-xl border-2 p-3 text-center text-sm font-semibold"
                           :class="type === '{{ $val }}' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 text-ink-600'">
                        <input type="radio" name="product_type" value="{{ $val }}" x-model="type" class="sr-only">{{ $lbl }}
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Customer --}}
        <div class="admin-card space-y-3">
            <h2 class="text-sm font-bold text-ink-700">Customer</h2>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">First Name *</label>
                    <input type="text" name="first_name" class="input" value="{{ old('first_name') }}" required>
                </div>
                <div>
                    <label class="label">Last Name</label>
                    <input type="text" name="last_name" class="input" value="{{ old('last_name') }}">
                </div>
                <div>
                    <label class="label">Email</label>
                    <input type="email" name="email" class="input" value="{{ old('email') }}"
                           placeholder="Links to existing account if matched">
                </div>
                <div>
                    <label class="label">Phone</label>
                    <input type="text" name="phone" class="input" value="{{ old('phone') }}">
                </div>
            </div>
        </div>

        {{-- Package details --}}
        <div class="admin-card space-y-3" x-show="type === 'package'" x-cloak>
            <h2 class="text-sm font-bold text-ink-700">Package Details</h2>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">Package (optional)</label>
                    <select name="package_id" class="input">
                        <option value="">— Custom / none —</option>
                        @foreach ($packages as $p)
                            <option value="{{ $p->id }}" @selected(old('package_id') == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Custom Package Name</label>
                    <input type="text" name="package_name" class="input" value="{{ old('package_name') }}"
                           placeholder="Used when no package selected">
                </div>
                <div>
                    <label class="label">Travel Start Date</label>
                    <input type="date" name="travel_date" class="input" value="{{ old('travel_date') }}">
                </div>
                <div>
                    <label class="label">Rooms</label>
                    <input type="number" name="rooms" min="0" max="99" class="input" value="{{ old('rooms', 1) }}">
                </div>
                <div>
                    <label class="label">Adults</label>
                    <input type="number" name="adults" min="0" max="99" class="input" value="{{ old('adults', 2) }}">
                </div>
                <div>
                    <label class="label">Children</label>
                    <input type="number" name="children" min="0" max="99" class="input" value="{{ old('children', 0) }}">
                </div>
            </div>
        </div>

        {{-- Hotel details --}}
        <div class="admin-card space-y-3" x-show="type === 'hotel'" x-cloak>
            <h2 class="text-sm font-bold text-ink-700">Hotel Details</h2>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">Hotel (optional)</label>
                    <select name="hotel_id" class="input">
                        <option value="">— Custom / none —</option>
                        @foreach ($hotels as $h)
                            <option value="{{ $h->id }}" @selected(old('hotel_id') == $h->id)>{{ $h->name }}@if ($h->city) — {{ $h->city }}@endif</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Custom Hotel Name</label>
                    <input type="text" name="hotel_name" class="input" value="{{ old('hotel_name') }}">
                </div>
                <div>
                    <label class="label">Room Type</label>
                    <input type="text" name="room_type" class="input" value="{{ old('room_type') }}" placeholder="Deluxe, Suite…">
                </div>
                <div>
                    <label class="label">Meal Plan</label>
                    <select name="meal_plan" class="input">
                        @foreach (['room_only' => 'Room Only', 'breakfast' => 'Breakfast', 'half_board' => 'Half Board', 'full_board' => 'Full Board'] as $mk => $ml)
                            <option value="{{ $mk }}" @selected(old('meal_plan') == $mk)>{{ $ml }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Check-in</label>
                    <input type="date" name="check_in" class="input" value="{{ old('check_in') }}">
                </div>
                <div>
                    <label class="label">Check-out</label>
                    <input type="date" name="check_out" class="input" value="{{ old('check_out') }}">
                </div>
                <div>
                    <label class="label">Rooms</label>
                    <input type="number" name="rooms" min="0" max="99" class="input" value="{{ old('rooms', 1) }}">
                </div>
            </div>
        </div>

        {{-- Cab details --}}
        <div class="admin-card space-y-3" x-show="type === 'cab'" x-cloak>
            <h2 class="text-sm font-bold text-ink-700">Cab / Transfer Details</h2>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">Vehicle (optional)</label>
                    <select name="vehicle_id" class="input">
                        <option value="">— Custom / none —</option>
                        @foreach ($vehicles as $v)
                            <option value="{{ $v->id }}" @selected(old('vehicle_id') == $v->id)>{{ $v->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Custom Vehicle Name</label>
                    <input type="text" name="vehicle_name" class="input" value="{{ old('vehicle_name') }}">
                </div>
                <div>
                    <label class="label">Pickup Location</label>
                    <input type="text" name="pickup_location" class="input" value="{{ old('pickup_location') }}">
                </div>
                <div>
                    <label class="label">Drop Location</label>
                    <input type="text" name="drop_location" class="input" value="{{ old('drop_location') }}">
                </div>
                <div>
                    <label class="label">Pickup Date &amp; Time</label>
                    <input type="datetime-local" name="pickup_datetime" class="input" value="{{ old('pickup_datetime') }}">
                </div>
                <div>
                    <label class="label">Trip Type</label>
                    <select name="trip_type" class="input">
                        @foreach (['one_way' => 'One Way', 'round_trip' => 'Round Trip', 'local' => 'Local', 'airport' => 'Airport'] as $tk => $tl)
                            <option value="{{ $tk }}" @selected(old('trip_type') == $tk)>{{ $tl }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Flight details --}}
        <div class="admin-card space-y-3" x-show="type === 'flight'" x-cloak>
            <h2 class="text-sm font-bold text-ink-700">Flight Details</h2>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">Airline</label>
                    <input type="text" name="airline" class="input" value="{{ old('airline') }}" placeholder="IndiGo, Air India…">
                </div>
                <div>
                    <label class="label">Flight Number</label>
                    <input type="text" name="flight_number" class="input" value="{{ old('flight_number') }}">
                </div>
                <div>
                    <label class="label">Origin</label>
                    <input type="text" name="origin" class="input" value="{{ old('origin') }}" placeholder="DEL">
                </div>
                <div>
                    <label class="label">Destination</label>
                    <input type="text" name="destination" class="input" value="{{ old('destination') }}" placeholder="SXR">
                </div>
                <div class="col-span-2">
                    <label class="label">Departure Date &amp; Time</label>
                    <input type="datetime-local" name="depart_at" class="input" value="{{ old('depart_at') }}">
                </div>
            </div>
        </div>

        {{-- Pricing --}}
        <div class="admin-card space-y-3"
             x-data="{ sub: {{ (float) old('subtotal', 0) }}, tax: {{ (float) old('tax_amount', 0) }}, disc: {{ (float) old('discount_amount', 0) }},
                       get total() { return Math.max(0, (parseFloat(this.sub)||0) + (parseFloat(this.tax)||0) - (parseFloat(this.disc)||0)); } }">
            <h2 class="text-sm font-bold text-ink-700">Pricing</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                    <label class="label">Subtotal (₹) *</label>
                    <input type="number" step="0.01" min="0" name="subtotal" class="input" x-model="sub" required>
                </div>
                <div>
                    <label class="label">Tax (₹)</label>
                    <input type="number" step="0.01" min="0" name="tax_amount" class="input" x-model="tax">
                </div>
                <div>
                    <label class="label">Discount (₹)</label>
                    <input type="number" step="0.01" min="0" name="discount_amount" class="input" x-model="disc">
                </div>
                <div>
                    <label class="label">Currency</label>
                    <input type="text" name="currency" maxlength="3" class="input uppercase" value="{{ old('currency', 'INR') }}">
                </div>
            </div>
            <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-2 text-sm">
                <span class="font-semibold text-ink-600">Total payable</span>
                <span class="font-display text-lg font-bold text-ink-900">₹<span x-text="total.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></span>
            </div>
        </div>

        {{-- Status + notes --}}
        <div class="admin-card space-y-3">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">Status *</label>
                    <select name="status" class="input">
                        <option value="confirmed" @selected(old('status', 'confirmed') === 'confirmed')>Confirmed (offline / paid)</option>
                        <option value="payment_pending" @selected(old('status') === 'payment_pending')>Payment Pending</option>
                    </select>
                    <p class="mt-1 text-[11px] text-ink-400">Confirmed sends a confirmation to the customer if email/phone is set.</p>
                </div>
            </div>
            <div>
                <label class="label">Internal Notes</label>
                <textarea name="notes" rows="2" class="input" placeholder="Optional — visible to staff only">{{ old('notes') }}</textarea>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.bookings.index') }}" class="btn-ghost btn-md">Cancel</a>
            <button type="submit" class="btn-primary btn-md">Create Booking</button>
        </div>
    </form>
</div>
@endsection

@extends('layouts.admin')
@section('pageTitle', ($room->exists ? 'Edit Room' : 'Add Room') . ' — ' . $hotel->name)

@section('content')
    <h1 class="font-display text-xl font-bold">{{ $room->exists ? 'Edit Room' : 'Add Room' }} — {{ $hotel->name }}</h1>

    <form action="{{ $room->exists ? route('admin.hotels.rooms.update', [$hotel, $room]) : route('admin.hotels.rooms.store', $hotel) }}"
          method="POST" class="admin-card mt-4 max-w-3xl">
        @csrf
        @if ($room->exists) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Room Type</label><input type="text" name="room_type" class="input" value="{{ old('room_type', $room->room_type) }}" placeholder="Deluxe Double" required></div>
            <div>
                <label class="label">Meal Plan</label>
                <select name="meal_plan" class="input">
                    @foreach (['room_only' => 'Room Only', 'breakfast' => 'Breakfast Included', 'half_board' => 'Half Board', 'full_board' => 'Full Board'] as $k => $label)
                        <option value="{{ $k }}" @selected(old('meal_plan', $room->meal_plan ?? 'room_only') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="label">Max Adults</label><input type="number" name="max_adults" class="input" value="{{ old('max_adults', $room->max_adults ?? 2) }}" min="1"></div>
            <div><label class="label">Max Children</label><input type="number" name="max_children" class="input" value="{{ old('max_children', $room->max_children ?? 1) }}" min="0"></div>
            <div><label class="label">Base Price / night (₹)</label><input type="number" step="any" name="base_price" class="input" value="{{ old('base_price', $room->base_price) }}" required></div>
            <div><label class="label">Extra Bed Price (₹)</label><input type="number" step="any" name="extra_bed_price" class="input" value="{{ old('extra_bed_price', $room->extra_bed_price) }}"></div>
            <div><label class="label">Child Price / night (₹)</label><input type="number" step="any" name="child_price" class="input" value="{{ old('child_price', $room->child_price) }}"><p class="mt-1 text-xs text-ink-400">Per child aged 2-12 sharing the room. Leave blank if children stay free.</p></div>
            <div><label class="label">Total Rooms (inventory)</label><input type="number" name="total_rooms" class="input" value="{{ old('total_rooms', $room->total_rooms ?? 5) }}" min="1"></div>
            <div><label class="label">Photo</label><x-admin.image-upload name="photo" :value="old('photo', $room->photo)" folder="hotels" /></div>
            <div class="sm:col-span-2"><label class="label">Description</label><textarea name="description" rows="3" class="input">{{ old('description', $room->description) }}</textarea></div>
            <div class="sm:col-span-2"><label class="label">Room Amenities (one per line)</label><textarea name="amenities" rows="4" class="input">{{ old('amenities', is_array($room->amenities) ? implode("\n", $room->amenities) : '') }}</textarea></div>
            <div>
                <label class="label">Status</label>
                <select name="status" class="input">
                    <option value="active" @selected(old('status', $room->status ?? 'active') === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $room->status ?? 'active') === 'inactive')>Inactive</option>
                </select>
            </div>
        </div>

        <div class="mt-5 flex gap-2">
            <button class="btn-primary btn-md">{{ $room->exists ? 'Save Changes' : 'Add Room' }}</button>
            <a href="{{ route('admin.hotels.rooms.index', $hotel) }}" class="btn-ghost btn-md">Back</a>
        </div>
    </form>
@endsection

@extends('layouts.admin')
@section('pageTitle', ($hotel->exists ? 'Edit: ' . $hotel->name : 'New Hotel'))

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="font-display text-xl font-bold">{{ $hotel->exists ? 'Edit: ' . $hotel->name : 'New Hotel' }}</h1>
        @if ($hotel->exists && auth('admin')->user()?->can('marketing.assets'))
            <a href="{{ route('admin.studio.create', ['product_type' => 'hotel', 'product_id' => $hotel->id]) }}" class="btn-primary btn-sm">✨ Create Ad</a>
        @endif
    </div>

    <form action="{{ $hotel->exists ? route('admin.hotels.update', $hotel) : route('admin.hotels.store') }}" method="POST" class="admin-card mt-4">
        @csrf
        @if ($hotel->exists) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Hotel Name</label><input type="text" name="name" class="input" value="{{ old('name', $hotel->name) }}" required></div>
            <div>
                <label class="label">Destination</label>
                <select name="destination_id" class="input">
                    <option value="">— None —</option>
                    @foreach ($destinations as $destination)
                        <option value="{{ $destination->id }}" @selected(old('destination_id', $hotel->destination_id) == $destination->id)>{{ $destination->name }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="label">City</label><input type="text" name="city" class="input" value="{{ old('city', $hotel->city) }}"></div>
            <div><label class="label">Address</label><input type="text" name="address" class="input" value="{{ old('address', $hotel->address) }}"></div>
            <div>
                <label class="label">Star Rating</label>
                <select name="star_rating" class="input">
                    @for ($i = 1; $i <= 5; $i++)
                        <option value="{{ $i }}" @selected(old('star_rating', $hotel->star_rating ?? 3) == $i)>{{ $i }} Star</option>
                    @endfor
                </select>
            </div>
            <div><label class="label">Starting Price (₹)</label><input type="number" step="any" name="starting_price" class="input" value="{{ old('starting_price', $hotel->starting_price) }}"></div>
            <div>
                <label class="label">Supplier (API source — optional)</label>
                <select name="supplier_id" class="input">
                    <option value="">Manual (admin-managed inventory)</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(old('supplier_id', $hotel->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="label">Supplier Hotel Code</label><input type="text" name="supplier_code" class="input" value="{{ old('supplier_code', $hotel->supplier_code) }}"></div>
            <div><label class="label">Cover Image</label><x-admin.image-upload name="cover_image" :value="old('cover_image', $hotel->cover_image)" folder="hotels" /></div>
            <div><label class="label">Photos</label><x-admin.multi-image-upload name="photos" :values="old('photos', $hotel->photos ?? [])" folder="hotels" /></div>
            <div class="sm:col-span-2"><label class="label">Short Description</label><input type="text" name="short_description" class="input" value="{{ old('short_description', $hotel->short_description) }}"></div>
            <div class="sm:col-span-2"><label class="label">Full Description</label><textarea name="description" rows="5" class="input">{{ old('description', $hotel->description) }}</textarea></div>
            <div><label class="label">Amenities (one per line)</label><textarea name="amenities" rows="5" class="input">{{ old('amenities', is_array($hotel->amenities) ? implode("\n", $hotel->amenities) : '') }}</textarea></div>
            <div><label class="label">Policies (one per line)</label><textarea name="policies" rows="5" class="input">{{ old('policies', is_array($hotel->policies) ? implode("\n", $hotel->policies) : '') }}</textarea></div>
            <label class="flex items-end gap-2 pb-2 text-sm font-semibold">
                <input type="checkbox" name="is_featured" value="1" class="accent-brand-600" @checked(old('is_featured', $hotel->is_featured ?? false))> Featured
            </label>
            <div>
                <label class="label">Status</label>
                <select name="status" class="input">
                    <option value="active" @selected(old('status', $hotel->status ?? 'active') === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $hotel->status ?? 'active') === 'inactive')>Inactive</option>
                </select>
            </div>
        </div>

        <div class="mt-5 flex gap-2">
            <button class="btn-primary btn-md">{{ $hotel->exists ? 'Save Changes' : 'Create Hotel' }}</button>
            <a href="{{ route('admin.hotels.index') }}" class="btn-ghost btn-md">Back</a>
        </div>
    </form>
@endsection

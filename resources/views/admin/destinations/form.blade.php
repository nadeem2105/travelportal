@extends('layouts.admin')
@section('pageTitle', ($destination->exists ? 'Edit: ' . $destination->name : 'New Destination'))

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="font-display text-xl font-bold">{{ $destination->exists ? 'Edit: ' . $destination->name : 'New Destination' }}</h1>
        @if ($destination->exists && auth('admin')->user()?->can('marketing.assets'))
            <a href="{{ route('admin.studio.create', ['product_type' => 'destination', 'product_id' => $destination->id]) }}" class="btn-primary btn-sm">✨ Create Ad</a>
        @endif
    </div>

    <form action="{{ $destination->exists ? route('admin.destinations.update', $destination) : route('admin.destinations.store') }}"
          method="POST" class="admin-card mt-4">
        @csrf
        @if ($destination->exists) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Name</label><input type="text" name="name" class="input" value="{{ old('name', $destination->name) }}" required></div>
            <div><label class="label">Region</label><input type="text" name="region" class="input" value="{{ old('region', $destination->region) }}" placeholder="Kashmir Valley"></div>
            <div><label class="label">Cover Image</label><x-admin.image-upload name="cover_image" :value="old('cover_image', $destination->cover_image)" folder="destinations" /></div>
            <div><label class="label">Famous For</label><input type="text" name="famous_for" class="input" value="{{ old('famous_for', $destination->famous_for) }}" placeholder="Lakes, Houseboats & More"></div>
            <div><label class="label">Best Time to Visit</label><input type="text" name="best_time" class="input" value="{{ old('best_time', $destination->best_time) }}" placeholder="March – October"></div>
            <div><label class="label">Altitude</label><input type="text" name="altitude" class="input" value="{{ old('altitude', $destination->altitude) }}" placeholder="1,585 m"></div>
            <div><label class="label">Latitude</label><input type="number" step="any" name="latitude" class="input" value="{{ old('latitude', $destination->latitude) }}"></div>
            <div><label class="label">Longitude</label><input type="number" step="any" name="longitude" class="input" value="{{ old('longitude', $destination->longitude) }}"></div>
            <div class="sm:col-span-2"><label class="label">Short Description</label><input type="text" name="short_description" class="input" value="{{ old('short_description', $destination->short_description) }}"></div>
            <div class="sm:col-span-2"><label class="label">Full Description</label><textarea name="description" rows="6" class="input">{{ old('description', $destination->description) }}</textarea></div>
            <div><label class="label">Gallery</label><x-admin.multi-image-upload name="gallery" :values="old('gallery', $destination->gallery ?? [])" folder="destinations" /></div>
            <div><label class="label">Places to Visit (one per line)</label><textarea name="places_to_visit" rows="3" class="input">{{ old('places_to_visit', is_array($destination->places_to_visit) ? implode("\n", $destination->places_to_visit) : '') }}</textarea></div>
            <div><label class="label">Things to Do (one per line)</label><textarea name="things_to_do" rows="3" class="input">{{ old('things_to_do', is_array($destination->things_to_do) ? implode("\n", $destination->things_to_do) : '') }}</textarea></div>
            <div><label class="label">Sort Order</label><input type="number" name="sort_order" class="input" value="{{ old('sort_order', $destination->sort_order ?? 0) }}"></div>
            <label class="flex items-end gap-2 pb-2 text-sm font-semibold">
                <input type="checkbox" name="is_featured" value="1" class="accent-brand-600" @checked(old('is_featured', $destination->is_featured ?? false))> Featured
            </label>
            <div>
                <label class="label">Status</label>
                <select name="status" class="input">
                    <option value="active" @selected(old('status', $destination->status ?? 'active') === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $destination->status ?? 'active') === 'inactive')>Inactive</option>
                </select>
            </div>
        </div>

        <div class="mt-5 flex gap-2">
            <button class="btn-primary btn-md">{{ $destination->exists ? 'Save Changes' : 'Create Destination' }}</button>
            <a href="{{ route('admin.destinations.index') }}" class="btn-ghost btn-md">Back</a>
        </div>
    </form>
@endsection

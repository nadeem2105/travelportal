@extends('layouts.admin')
@section('pageTitle', 'Rooms: ' . $hotel->name)

@section('content')
    <a href="{{ route('admin.hotels.index') }}" class="text-sm text-ink-500 hover:text-brand-700">← All Hotels</a>

    <div class="mt-2 flex items-center justify-between">
        <h1 class="font-display text-xl font-bold">Rooms — {{ $hotel->name }}</h1>
        <a href="{{ route('admin.hotels.rooms.create', $hotel) }}" class="btn-primary btn-md">+ Add Room</a>
    </div>

    <div class="admin-card mt-4 overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th>Room Type</th><th>Meal Plan</th><th>Capacity</th><th>Base Price</th><th>Inventory</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
                @forelse ($hotel->rooms as $room)
                    <tr>
                        <td class="font-bold">{{ $room->room_type }}</td>
                        <td>{{ label_case($room->meal_plan) }}</td>
                        <td>{{ $room->max_adults }} adults + {{ $room->max_children }} children</td>
                        <td>{{ money($room->base_price) }}</td>
                        <td>{{ $room->total_rooms }}</td>
                        <td><span class="status-pill {{ status_pill_class($room->status) }}">{{ ucfirst($room->status) }}</span></td>
                        <td class="text-right">
                            <a href="{{ route('admin.hotels.rooms.edit', [$hotel, $room]) }}" class="font-bold text-brand-600">Edit</a>
                            <form action="{{ route('admin.hotels.rooms.destroy', [$hotel, $room]) }}" method="POST" class="inline" onclick="return confirm('Remove room?')">
                                @csrf @method('DELETE')
                                <button class="ml-3 font-bold text-rose-500">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-ink-500">No rooms — add one to make this hotel bookable</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

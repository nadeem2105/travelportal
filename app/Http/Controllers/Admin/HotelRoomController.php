<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class HotelRoomController extends Controller
{
    public function index(Hotel $hotel)
    {
        return view('admin.hotels.rooms', ['hotel' => $hotel->load('rooms')]);
    }

    public function create(Hotel $hotel)
    {
        return view('admin.hotels.room_form', ['hotel' => $hotel, 'room' => new HotelRoom()]);
    }

    public function store(Request $request, Hotel $hotel)
    {
        $hotel->rooms()->create($this->validated($request));

        ActivityLogger::log('create', 'hotels', "Room added to {$hotel->name}");

        return redirect()->route('admin.hotels.rooms.index', $hotel)->with('success', 'Room added.');
    }

    public function edit(Hotel $hotel, HotelRoom $room)
    {
        return view('admin.hotels.room_form', ['hotel' => $hotel, 'room' => $room]);
    }

    public function update(Request $request, Hotel $hotel, HotelRoom $room)
    {
        $room->update($this->validated($request));

        ActivityLogger::log('update', 'hotels', "Room updated for {$hotel->name}");

        return redirect()->route('admin.hotels.rooms.index', $hotel)->with('success', 'Room updated.');
    }

    public function destroy(Hotel $hotel, HotelRoom $room)
    {
        $room->delete();

        return redirect()->route('admin.hotels.rooms.index', $hotel)->with('success', 'Room removed.');
    }

    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'room_type' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'max_adults' => 'required|integer|min:1|max:10',
            'max_children' => 'required|integer|min:0|max:6',
            'base_price' => 'required|numeric|min:0',
            'extra_bed_price' => 'nullable|numeric|min:0',
            'child_price' => 'nullable|numeric|min:0',
            'meal_plan' => 'required|in:room_only,breakfast,half_board,full_board',
            'amenities' => 'nullable|string|max:1000',
            'total_rooms' => 'required|integer|min:1|max:500',
            'photo' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        $validated['amenities'] = ! empty($validated['amenities'])
            ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $validated['amenities']))))
            : null;

        return $validated;
    }
}

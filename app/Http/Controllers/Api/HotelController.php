<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Services\HotelEngine;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    public function __construct(protected HotelEngine $engine)
    {
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'destination' => 'required|string|max:80',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
        ]);

        $response = $this->engine->search($validated);

        return response()->json([
            'data' => collect($response['results'])->map(fn ($h) => [
                'hotel_code' => $h['hotel_code'],
                'name' => $h['name'],
                'city' => $h['city'],
                'star_rating' => $h['star_rating'],
                'amenities' => $h['amenities'],
                'price_per_night' => ['amount' => $h['display_price'] ?? $h['starting_price'], 'currency' => 'INR'],
            ]),
            'meta' => ['count' => $response['count']],
        ]);
    }

    public function show(Hotel $hotel)
    {
        if ($hotel->status !== 'active') {
            abort(404, 'Hotel not found or inactive.');
        }

        $hotel->load(['destination', 'rooms' => fn ($q) => $q->where('status', 'active')]);

        return response()->json([
            'data' => [
                'id' => $hotel->id,
                'name' => $hotel->name,
                'slug' => $hotel->slug,
                'destination' => $hotel->destination?->name,
                'city' => $hotel->city,
                'address' => $hotel->address,
                'star_rating' => $hotel->star_rating,
                'short_description' => $hotel->short_description,
                'description' => $hotel->description,
                'amenities' => $hotel->amenities ?? [],
                'policies' => $hotel->policies ?? [],
                'photos' => array_values(array_filter(array_merge([$hotel->cover_image], $hotel->photos ?? []))),
                'starting_price' => $hotel->starting_price,
                'rating' => $hotel->averageRating(),
                'review_count' => $hotel->reviews()->approved()->count(),
                'rooms_count' => $hotel->rooms->count(),
            ],
        ]);
    }

    public function rooms(Hotel $hotel, Request $request)
    {
        if ($hotel->status !== 'active') {
            abort(404, 'Hotel not found.');
        }

        $validated = $request->validate([
            'check_in' => 'nullable|date|after_or_equal:today',
            'check_out' => 'nullable|date|after:check_in',
            'rooms' => 'nullable|integer|min:1|max:5',
            'adults' => 'nullable|integer|min:1|max:10',
            'children' => 'nullable|integer|min:0|max:5',
        ]);

        $params = [
            'check_in' => $validated['check_in'] ?? now()->addDays(7)->format('Y-m-d'),
            'check_out' => $validated['check_out'] ?? now()->addDays(9)->format('Y-m-d'),
            'rooms' => (int) ($validated['rooms'] ?? 1),
            'adults' => (int) ($validated['adults'] ?? 2),
            'children' => (int) ($validated['children'] ?? 0),
        ];

        $roomsResponse = $this->engine->getRooms('MANUAL-' . $hotel->id, $params, $hotel->supplier_id);

        return response()->json([
            'data' => $roomsResponse['rooms'] ?? [],
            'params' => $params,
            'hotel' => [
                'id' => $hotel->id,
                'name' => $hotel->name,
                'slug' => $hotel->slug,
            ],
        ]);
    }
}

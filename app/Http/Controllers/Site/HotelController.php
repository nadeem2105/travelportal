<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Hotel;
use App\Services\HotelEngine;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    public function __construct(protected HotelEngine $engine)
    {
    }

    public function index(Request $request)
    {
        return view('hotels.index', [
            'seo' => app(\App\Services\SeoService::class)->forPage('hotels'),
            'initialTab' => 'hotels',
            'featuredHotels' => Hotel::where('status', 'active')->orderByDesc('is_featured')->limit(6)->get(),
            'destinations' => Destination::where('status', 'active')->orderBy('sort_order')->get(),
        ]);
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'destination' => 'required|string|max:80',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'nullable|integer|min:1|max:20',
            'children' => 'nullable|integer|min:0|max:12',
            'infants' => 'nullable|integer|min:0|max:6',
            'rooms' => 'nullable|integer|min:1|max:10',
            // Legacy single-field support (older links / bookmarks).
            'guests' => 'nullable|string|max:6',
        ]);

        if ($request->filled('adults')) {
            $adults = (int) $validated['adults'];
            $rooms = min((int) ($validated['rooms'] ?? 1), $adults);
        } else {
            [$adults, $rooms] = $this->parseGuests($validated['guests'] ?? '2');
        }

        $params = [
            'destination' => $validated['destination'],
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'],
            'adults' => $adults,
            'children' => (int) ($validated['children'] ?? 0),
            'infants' => (int) ($validated['infants'] ?? 0),
            'rooms' => max(1, $rooms),
        ];

        $search = $this->engine->search($params);

        session(['hotel_search_params' => $params]);

        \App\Services\AnalyticsService::track('search_hotel', [
            'product_type' => 'hotel',
            'destination' => $validated['destination'],
            'results_count' => $search['count'],
        ]);

        return view('hotels.results', [
            'seo' => app(\App\Services\SeoService::class)->forPage('hotels', null, [
                'title' => 'Hotels in ' . $validated['destination'],
            ]),
            'params' => $params,
            'hotels' => collect($search['results']),
            'count' => $search['count'],
        ]);
    }

    public function show(Request $request, Hotel $hotel)
    {
        if ($hotel->status !== 'active') {
            abort(404);
        }

        \App\Services\AnalyticsService::track('view_hotel', [
            'product_type' => 'hotel',
            'product_id' => $hotel->id,
            'hotel_name' => $hotel->name,
        ]);

        $params = session('hotel_search_params', [
            'check_in' => now()->addDays(7)->format('Y-m-d'),
            'check_out' => now()->addDays(9)->format('Y-m-d'),
            'rooms' => 1,
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
        ]);

        $roomsResponse = $this->engine->getRooms('MANUAL-' . $hotel->id, $params, $hotel->supplier_id);

        return view('hotels.show', [
            'seo' => app(\App\Services\SeoService::class)->forPage('hotels', $hotel, [
                'title' => $hotel->name . ' | Book Online',
                'description' => $hotel->short_description,
            ]),
            'hotel' => $hotel,
            'rooms' => $roomsResponse['rooms'] ?? [],
            'params' => $params,
            'reviews' => $hotel->reviews()->approved()->latest()->limit(10)->get(),
            'rating' => $hotel->averageRating(),
        ]);
    }

    public function book(Request $request)
    {
        $validated = $request->validate([
            'hotel_id' => 'required|integer|exists:hotels,id',
            'room_id' => 'required|integer|exists:hotel_rooms,id',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'rooms' => 'required|integer|min:1|max:5',
            'guest_name' => 'required|string|max:80',
            'guest_email' => 'required|email',
            'guest_phone' => 'required|string|max:15',
        ]);

        $hotel = Hotel::with('rooms')->findOrFail($validated['hotel_id']);
        $room = $hotel->rooms->firstWhere('id', (int) $validated['room_id']);

        if (! $room || $room->status !== 'active') {
            return back()->with('error', 'The selected room is unavailable.');
        }

        // Occupancy from the original search (falls back to sensible defaults).
        $search = session('hotel_search_params', []);
        $adults = (int) ($search['adults'] ?? 2);
        $children = (int) ($search['children'] ?? 0);
        $infants = (int) ($search['infants'] ?? 0);

        $nights = \Illuminate\Support\Carbon::parse($validated['check_in'])->diffInDays($validated['check_out']);

        // Server-side price recalculation (occupancy included so child charges apply).
        $supplier = $hotel->supplier_id ? \App\Models\Supplier::find($hotel->supplier_id) : null;
        $params = [
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'],
            'rooms' => $validated['rooms'],
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
        ];
        $roomsResponse = $this->engine->getRooms('MANUAL-' . $hotel->id, $params, $hotel->supplier_id);
        $roomData = collect($roomsResponse['rooms'] ?? [])->firstWhere('room_id', $room->id);

        $unitPrice = $roomData['display_price'] ?? (float) $room->base_price;
        $supplierCost = $roomData['supplier_cost'] ?? (float) $room->base_price;
        $totalCost = round($supplierCost * $nights * $validated['rooms'], 2);

        $pricing = app(\App\Services\PricingService::class)->calculate($totalCost, 'hotel', $hotel->supplier_id);

        $booking = app(\App\Services\BookingService::class)->create([
            'product_type' => 'hotel',
            'product_id' => $hotel->id,
            'supplier_id' => $hotel->supplier_id,
            'pricing' => $pricing,
            'items' => [[
                'item_type' => 'hotel_room',
                'item_id' => $room->id,
                'name' => $hotel->name . ' · ' . $room->room_type,
                'quantity' => (int) $nights * $validated['rooms'],
                'unit_price' => $unitPrice,
                'total_price' => $pricing['total'],
                'details' => ['meal_plan' => $room->meal_plan, 'nights' => $nights, 'rooms' => $validated['rooms']],
            ]],
            'travellers' => [[
                'traveller_type' => 'adult',
                'title' => 'Mr',
                'first_name' => strtok($validated['guest_name'], ' ') ?: $validated['guest_name'],
                'last_name' => strstr($validated['guest_name'], ' ') ? substr(strstr($validated['guest_name'], ' '), 1) : '',
                'is_primary' => true,
            ]],
            'contact' => ['email' => $validated['guest_email'], 'phone' => $validated['guest_phone'], 'first_name' => strtok($validated['guest_name'], ' ')],
            'hotel' => [
                'hotel_id' => $hotel->id,
                'hotel_name' => $hotel->name,
                'room_type' => $room->room_type,
                'check_in' => $validated['check_in'],
                'check_out' => $validated['check_out'],
                'rooms' => $validated['rooms'],
                'guests' => ['adults' => $adults, 'children' => $children, 'infants' => $infants],
                'meal_plan' => $room->meal_plan,
            ],
        ]);

        \App\Services\AnalyticsService::track('booking_start', [
            'product_type' => 'hotel',
            'product_id' => $booking->product_id,
            'booking_reference' => $booking->booking_reference,
            'value' => (float) $booking->total_amount,
            'currency' => $booking->currency ?: 'INR',
        ]);

        return redirect()->route('checkout.show', $booking);
    }

    protected function parseGuests(string $value): array
    {
        $guests = (int) $value;
        $adults = max(1, $guests);
        $rooms = max(1, (int) ceil($adults / 2));

        return [$adults, $rooms];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'data' => $request->user()->bookings()
                ->with('items')
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->paginate($request->integer('per_page', 15) ?: 15)
                ->through(fn (Booking $b) => [
                    'reference' => $b->booking_reference,
                    'type' => $b->product_type,
                    'status' => $b->status,
                    'total' => ['amount' => $b->total_amount, 'currency' => $b->currency],
                    'booked_at' => $b->booked_at?->toIso8601String(),
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_type' => 'required|in:package,hotel,flight,cab',
            'product_id' => 'required|integer',
            'travellers' => 'required|array|min:1',
            'travellers.*.first_name' => 'required|string|max:60',
            'travellers.*.last_name' => 'nullable|string|max:60',
            'travellers.*.traveller_type' => 'nullable|in:adult,child,infant',
            'contact' => 'required|array',
            'contact.email' => 'required|email',
            'contact.phone' => 'required|string|max:20',
            'contact.first_name' => 'nullable|string|max:60',
            'details' => 'nullable|array',
        ]);

        $user = $request->user();
        $bookingService = app(BookingService::class);
        $pricingService = app(\App\Services\PricingService::class);

        if ($validated['product_type'] === 'package') {
            $package = \App\Models\Package::where('status', 'active')->findOrFail($validated['product_id']);
            $travelDate = $validated['details']['departure_date'] ?? now()->addDays(14)->format('Y-m-d');
            $adults = count(array_filter($validated['travellers'], fn ($t) => ($t['traveller_type'] ?? 'adult') === 'adult')) ?: 1;
            $children = count(array_filter($validated['travellers'], fn ($t) => ($t['traveller_type'] ?? '') === 'child'));
            $pricePerPerson = $package->effectivePrice($travelDate);
            $totalCost = round(($pricePerPerson * $adults) + ($pricePerPerson * 0.7 * $children), 2);
            $pricing = $pricingService->calculate($totalCost, 'package', $package->supplier_id);

            $booking = $bookingService->create([
                'user_id' => $user->id,
                'product_type' => 'package',
                'product_id' => $package->id,
                'supplier_id' => $package->supplier_id,
                'pricing' => $pricing,
                'items' => [[
                    'item_type' => 'package_tour',
                    'item_id' => $package->id,
                    'name' => $package->name,
                    'quantity' => $adults + $children,
                    'unit_price' => $pricePerPerson,
                    'total_price' => $pricing['total'],
                ]],
                'travellers' => $validated['travellers'],
                'contact' => $validated['contact'],
                'package' => [
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'departure_date' => $travelDate,
                    'duration_days' => $package->duration_days,
                    'duration_nights' => $package->duration_nights,
                    'adults' => $adults,
                    'children' => $children,
                ],
            ]);
        } elseif ($validated['product_type'] === 'hotel') {
            $hotel = \App\Models\Hotel::with('rooms')->where('status', 'active')->findOrFail($validated['product_id']);
            $roomId = $validated['details']['room_id'] ?? $hotel->rooms->first()?->id;
            $room = $hotel->rooms->firstWhere('id', (int) $roomId);
            abort_unless($room && $room->status === 'active', 422, 'Room unavailable.');

            $checkIn = $validated['details']['check_in'] ?? now()->addDays(5)->format('Y-m-d');
            $checkOut = $validated['details']['check_out'] ?? now()->addDays(7)->format('Y-m-d');
            $roomsCount = (int) ($validated['details']['rooms'] ?? 1);
            $nights = max(1, \Carbon\Carbon::parse($checkIn)->diffInDays($checkOut));
            $totalCost = round((float) $room->base_price * $nights * $roomsCount, 2);
            $pricing = $pricingService->calculate($totalCost, 'hotel', $hotel->supplier_id);

            $booking = $bookingService->create([
                'user_id' => $user->id,
                'product_type' => 'hotel',
                'product_id' => $hotel->id,
                'supplier_id' => $hotel->supplier_id,
                'pricing' => $pricing,
                'items' => [[
                    'item_type' => 'hotel_room',
                    'item_id' => $room->id,
                    'name' => $hotel->name . ' · ' . $room->room_type,
                    'quantity' => $nights * $roomsCount,
                    'unit_price' => (float) $room->base_price,
                    'total_price' => $pricing['total'],
                    'details' => ['meal_plan' => $room->meal_plan, 'nights' => $nights, 'rooms' => $roomsCount],
                ]],
                'travellers' => $validated['travellers'],
                'contact' => $validated['contact'],
                'hotel' => [
                    'hotel_id' => $hotel->id,
                    'hotel_name' => $hotel->name,
                    'room_type' => $room->room_type,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'rooms' => $roomsCount,
                    'guests' => ['adults' => count($validated['travellers'])],
                    'meal_plan' => $room->meal_plan,
                ],
            ]);
        } else {
            $totalCost = (float) ($validated['details']['supplier_cost'] ?? 5000);
            $pricing = $pricingService->calculate($totalCost, $validated['product_type'], null);
            $booking = $bookingService->create([
                'user_id' => $user->id,
                'product_type' => $validated['product_type'],
                'product_id' => $validated['product_id'],
                'pricing' => $pricing,
                'items' => [[
                    'item_type' => $validated['product_type'] . '_booking',
                    'item_id' => $validated['product_id'],
                    'name' => ucfirst($validated['product_type']) . ' Booking',
                    'quantity' => 1,
                    'unit_price' => $pricing['total'],
                    'total_price' => $pricing['total'],
                ]],
                'travellers' => $validated['travellers'],
                'contact' => $validated['contact'],
            ]);
        }

        \App\Services\AnalyticsService::track('begin_checkout', [
            'product_type' => $booking->product_type,
            'product_id' => $booking->product_id,
            'booking_reference' => $booking->booking_reference,
            'amount' => (float) $booking->total_amount,
            'channel' => 'api',
        ]);

        return response()->json([
            'message' => 'Booking created successfully.',
            'data' => [
                'reference' => $booking->booking_reference,
                'status' => $booking->status,
                'type' => $booking->product_type,
                'total' => ['amount' => $booking->total_amount, 'currency' => $booking->currency],
                'price_breakdown' => $booking->price_breakdown,
                'checkout_url' => route('checkout.show', $booking),
            ],
        ], 201);
    }

    public function show(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        return response()->json([
            'data' => [
                'reference' => $booking->booking_reference,
                'type' => $booking->product_type,
                'status' => $booking->status,
                'items' => $booking->items->map(fn ($i) => ['name' => $i->name, 'quantity' => $i->quantity, 'total' => $i->total_price]),
                'travellers' => $booking->travellers->map(fn ($t) => ['name' => $t->full_name, 'type' => $t->traveller_type]),
                'price_breakdown' => $booking->price_breakdown,
                'total' => ['amount' => $booking->total_amount, 'currency' => $booking->currency],
            ],
        ]);
    }

    public function cancel(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        abort_unless($booking->isCancellable(), 422, 'Booking cannot be cancelled.');

        $validated = $request->validate(['reason' => 'required|string|min:10|max:500']);

        app(BookingService::class)->requestCancellation($booking, $validated['reason'], $request->user()->id);

        return response()->json(['message' => 'Cancellation request submitted.'], 202);
    }
}

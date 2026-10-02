<?php

namespace App\Services\Suppliers\Hotels;

use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\Supplier;
use App\Services\Suppliers\HotelSupplierInterface;
use Illuminate\Support\Carbon;

/**
 * Serves hotels managed manually from the Admin Panel (no external API).
 * Availability is derived from room inventory minus overlapping bookings.
 */
class ManualHotelSupplier implements HotelSupplierInterface
{
    public function __construct(protected Supplier $supplier, protected array $credentials = [])
    {
    }

    public function searchHotels(array $params): array
    {
        $query = Hotel::query()
            ->where('status', 'active')
            ->with('rooms')
            ->withCount(['reviews as review_count' => fn ($q) => $q->where('status', 'approved')]);

        if (! empty($params['destination'])) {
            $term = $params['destination'];
            $query->where(function ($q) use ($term) {
                $q->where('city', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhereHas('destination', fn ($d) => $d->where('name', 'like', "%{$term}%"));
            });
        }

        if (! empty($params['star_ratings'])) {
            $query->whereIn('star_rating', (array) $params['star_ratings']);
        }

        if (! empty($params['min_price'])) {
            $query->where(function ($q) use ($params) {
                $q->whereNull('starting_price')->orWhere('starting_price', '>=', $params['min_price']);
            });
        }

        if (! empty($params['max_price'])) {
            $query->where(function ($q) use ($params) {
                $q->whereNull('starting_price')->orWhere('starting_price', '<=', $params['max_price']);
            });
        }

        $hotels = $query->orderByDesc('is_featured')->orderBy('name')->limit(30)->get();

        $results = $hotels->map(fn (Hotel $hotel) => $this->mapHotel($hotel, $params))->values()->toArray();

        return ['success' => true, 'results' => $results, 'cache_ttl' => 120];
    }

    protected function mapHotel(Hotel $hotel, array $params): array
    {
        $cheapest = $hotel->rooms->where('status', 'active')->min('base_price');
        $rating = $hotel->reviews->where('status', 'approved');
        $roomsLeft = $hotel->rooms->where('status', 'active')->sum('total_rooms');

        return [
            'hotel_code' => 'MANUAL-' . $hotel->id,
            'supplier_id' => $this->supplier->id,
            'supplier_name' => $this->supplier->name,
            'id' => $hotel->id,
            'name' => $hotel->name,
            'slug' => $hotel->slug,
            'city' => $hotel->city,
            'destination' => $hotel->destination?->name,
            'star_rating' => $hotel->star_rating,
            'address' => $hotel->address,
            'cover_image' => $hotel->cover_image,
            'photos' => $hotel->photos ?? [],
            'amenities' => $hotel->amenities ?? [],
            'short_description' => $hotel->short_description,
            'starting_price' => $cheapest !== null ? round($cheapest * $this->seasonalFactor($params)) : null,
            'raw_starting_price' => $cheapest,
            'user_rating' => $rating->count() ? round($rating->avg('rating'), 1) : null,
            'review_count' => $rating->count(),
            'rooms_left' => $roomsLeft,
            'is_manual' => true,
        ];
    }

    /**
     * Simple seasonal demand factor controllable per-hotel later.
     */
    protected function seasonalFactor(array $params): float
    {
        $checkIn = isset($params['check_in']) ? Carbon::parse($params['check_in']) : now();

        // Kashmir peak: April–June & December get a seasonal uplift.
        // Off-peak never drops below the admin-set base price (factor floored at 1.0).
        return match (true) {
            in_array($checkIn->month, [4, 5, 6, 12]) => 1.2,
            default => 1.0,
        };
    }

    public function getHotelDetails(string $hotelCode, array $params): array
    {
        $hotel = $this->resolveHotel($hotelCode);

        if (! $hotel) {
            return ['success' => false, 'error' => 'Hotel not found.'];
        }

        $hotel->load('destination', 'rooms');

        return [
            'success' => true,
            'hotel' => $this->mapHotel($hotel, $params) + [
                'description' => $hotel->description,
                'policies' => $hotel->policies ?? [],
                'gallery' => array_merge([$hotel->cover_image], $hotel->photos ?? []),
            ],
        ];
    }

    public function getRooms(string $hotelCode, array $params): array
    {
        $hotel = $this->resolveHotel($hotelCode);

        if (! $hotel) {
            return ['success' => false, 'error' => 'Hotel not found.'];
        }

        $factor = $this->seasonalFactor($params);

        $rooms = $hotel->rooms->where('status', 'active')->map(function (HotelRoom $room) use ($factor, $params) {
            $available = $this->roomsAvailable($room, $params['check_in'] ?? null, $params['check_out'] ?? null);

            $baseCost = round($room->base_price * $factor);
            $extraAdults = max(0, (int) ($params['adults'] ?? 2) - $room->max_adults);
            $extraBedCost = ($extraAdults > 0 && $room->extra_bed_price) ? (float) $room->extra_bed_price : 0;

            // Children aged 2-12 sharing the room are charged per child per night.
            $children = (int) ($params['children'] ?? 0);
            $chargeableChildren = min($children, (int) $room->max_children);
            $childCost = ($chargeableChildren > 0 && $room->child_price)
                ? round((float) $room->child_price * $chargeableChildren)
                : 0;

            $supplierCost = $baseCost + $extraBedCost + $childCost;

            return [
                'room_code' => 'R-' . $room->id,
                'room_id' => $room->id,
                'room_type' => $room->room_type,
                'description' => $room->description,
                'meal_plan' => $room->meal_plan,
                'meal_plan_label' => $this->formatMealPlan($room->meal_plan),
                'max_adults' => $room->max_adults,
                'max_children' => $room->max_children,
                'photo' => $room->photo,
                'amenities' => $room->amenities ?? [],
                'supplier_cost' => $supplierCost,
                'base_price' => $baseCost,
                'extra_bed_price' => $room->extra_bed_price,
                'child_price' => $room->child_price,
                'child_cost' => $childCost,
                'chargeable_children' => $chargeableChildren,
                'available_rooms' => $available,
                'cancellation_policy' => $this->cancellationPolicy(),
            ];
        })->values()->toArray();

        return ['success' => true, 'rooms' => $rooms];
    }

    public function checkAvailability(string $hotelCode, string $roomCode, array $params): array
    {
        $room = HotelRoom::find((int) str_replace('R-', '', $roomCode));

        if (! $room || $room->status !== 'active') {
            return ['success' => false, 'error' => 'Room unavailable.'];
        }

        $available = $this->roomsAvailable($room, $params['check_in'] ?? null, $params['check_out'] ?? null);
        $needed = (int) ($params['rooms'] ?? 1);

        return $available >= $needed
            ? ['success' => true, 'available' => $available]
            : ['success' => false, 'error' => 'Only ' . $available . ' room(s) left for these dates.', 'available' => $available];
    }

    public function createBooking(string $hotelCode, string $roomCode, array $params, array $guests, array $contact): array
    {
        $availability = $this->checkAvailability($hotelCode, $roomCode, $params);

        if (! $availability['success']) {
            return $availability;
        }

        return [
            'success' => true,
            'supplier_booking_id' => 'MANUAL-HT-' . strtoupper(substr(md5(microtime()), 0, 10)),
            'status' => 'confirmed',
        ];
    }

    public function cancelBooking(string $supplierBookingId): array
    {
        return ['success' => true, 'status' => 'cancelled', 'refund_eta_days' => 5];
    }

    public function getBookingStatus(string $supplierBookingId): array
    {
        return ['success' => true, 'status' => 'confirmed'];
    }

    public function getRefundStatus(string $supplierBookingId): array
    {
        return ['success' => true, 'status' => 'processed'];
    }

    protected function resolveHotel(string $hotelCode): ?Hotel
    {
        $id = (int) str_replace('MANUAL-', '', $hotelCode);

        return Hotel::find($id);
    }

    protected function roomsAvailable(HotelRoom $room, ?string $checkIn, ?string $checkOut): int
    {
        if (! $checkIn || ! $checkOut) {
            return $room->total_rooms;
        }

        $overlapping = \App\Models\HotelBooking::join('bookings', 'bookings.id', '=', 'hotel_bookings.booking_id')
            ->where('hotel_bookings.hotel_id', $room->hotel_id)
            ->where('hotel_bookings.room_type', $room->room_type)
            ->whereIn('bookings.status', ['pending', 'payment_pending', 'confirmed'])
            ->where('hotel_bookings.check_in', '<', $checkOut)
            ->where('hotel_bookings.check_out', '>', $checkIn)
            ->sum('hotel_bookings.rooms');

        return max(0, $room->total_rooms - (int) $overlapping);
    }

    protected function cancellationPolicy(): array
    {
        return [
            'free_until' => 'Free cancellation up to 48 hours before check-in',
            'after' => 'One night charges apply within 48 hours of check-in',
            'no_show' => 'Full booking amount is non-refundable on no-show',
        ];
    }

    protected function formatMealPlan(?string $plan): string
    {
        return match ($plan) {
            'room_only' => 'EP (Room Only)',
            'breakfast' => 'CP (Bed & Breakfast)',
            'half_board' => 'MAP (Breakfast & Dinner)',
            'full_board' => 'AP (All Meals Included)',
            default => $plan ? ucfirst(str_replace('_', ' ', $plan)) : 'EP (Room Only)',
        };
    }
}

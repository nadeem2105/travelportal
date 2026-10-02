<?php

namespace App\Services\TripOperations;

use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripEvent;
use App\Services\ActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Builds and regenerates the operational Trip overlay (days + timeline events)
 * from an authoritative Booking. Reuses package_itineraries, Booking::hotelStayForDay(),
 * and the per-booking hotel/flight/cab snapshots. Never duplicates booking data —
 * it references it and snapshots only the day-by-day operational plan.
 */
class TripBuilder
{
    /**
     * Create (or fetch) the Trip row for a booking. Idempotent by booking_id.
     */
    public function ensureTrip(Booking $booking): Trip
    {
        $trip = Trip::where('booking_id', $booking->id)->first();
        if ($trip) {
            return $trip;
        }

        [$arrival, $departure, $days, $destination] = $this->deriveWindow($booking);

        return Trip::create([
            'booking_id' => $booking->id,
            'trip_reference' => $this->makeReference($booking),
            'status' => Trip::STATUS_UPCOMING,
            'arrival_date' => $arrival,
            'departure_date' => $departure,
            'total_days' => $days,
            'lead_customer_name' => $booking->user?->name ?: ($booking->contact['first_name'] ?? null),
            'lead_customer_phone' => $booking->contact['phone'] ?? $booking->user?->phone,
            'lead_customer_email' => $booking->user?->email ?: ($booking->contact['email'] ?? null),
            'destination_label' => $destination,
            'product_type' => $booking->product_type,
            'itinerary_version' => 1,
            'itinerary_generated' => false,
            'created_by' => auth('admin')->id(),
        ]);
    }

    /**
     * Build (or rebuild) the day-by-day plan + timeline events for a trip.
     * Bumps itinerary_version on every regeneration so downstream comms are versioned.
     */
    public function generateItinerary(Trip $trip, bool $regenerate = false): Trip
    {
        // Load the booking fully with the operational relations we need.
        $booking = Booking::with([
            'packageBooking.package.itineraries', 'packageBooking.package.destination',
            'bookingHotels', 'hotelBooking', 'cab.vehicle', 'flight', 'user',
        ])->find($trip->booking_id);

        if (! $booking) {
            return $trip;
        }

        if ($trip->itinerary_generated && ! $regenerate) {
            return $trip; // already built; caller must pass regenerate to rebuild
        }

        DB::transaction(function () use ($trip, $booking) {
            // Wipe prior generated plan (events + days) before rebuilding.
            TripEvent::where('trip_id', $trip->id)->delete();
            TripDay::where('trip_id', $trip->id)->delete();

            match ($booking->product_type) {
                'package' => $this->buildPackagePlan($trip, $booking),
                'hotel' => $this->buildHotelPlan($trip, $booking),
                'cab' => $this->buildCabPlan($trip, $booking),
                'flight' => $this->buildFlightPlan($trip, $booking),
                default => $this->buildGenericPlan($trip, $booking),
            };

            [$arrival, $departure, $days, $destination] = $this->deriveWindow($booking);
            $trip->forceFill([
                'arrival_date' => $arrival,
                'departure_date' => $departure,
                'total_days' => $days,
                'destination_label' => $destination,
                'itinerary_generated' => true,
                'itinerary_version' => $trip->itinerary_generated ? $trip->itinerary_version + 1 : 1,
            ])->save();
        });

        ActivityLogger::log('generate', 'trip_operations',
            "Generated itinerary v{$trip->itinerary_version} for trip {$trip->trip_reference}");

        return $trip->refresh();
    }

    /* ============================ builders ============================ */

    protected function buildPackagePlan(Trip $trip, Booking $booking): void
    {
        $pkgBooking = $booking->packageBooking;
        $package = $pkgBooking?->package;
        $start = $pkgBooking?->departure_date ? Carbon::parse($pkgBooking->departure_date) : Carbon::today();
        $itineraries = $package?->itineraries ?? collect();

        if ($itineraries->isEmpty()) {
            // No template days — create a single arrival day so the trip is still operable.
            $this->makeDay($trip, 1, $start, $package?->name ?? 'Arrival', null, null);
            $this->makeEvent($trip, null, 1, 'arrival', null, 'Guest arrival', null, 'both');

            return;
        }

        foreach ($itineraries as $day) {
            $date = $start->copy()->addDays($day->day_number - 1);
            $hotel = $booking->hotelStayForDay($day->day_number) ?: $day->overnight_stay;
            $meals = is_array($day->meals) ? implode(', ', $day->meals) : null;

            $tripDay = $this->makeDay($trip, $day->day_number, $date, $day->title, $day->description, $hotel, $meals);

            // First day → arrival marker.
            if ($day->day_number === 1) {
                $this->makeEvent($trip, $tripDay, 1, 'arrival', null,
                    'Arrival & welcome', $trip->destination_label, 'both', 0);
            }

            // The day's plan as an activity event (customer-visible).
            $this->makeEvent($trip, $tripDay, $day->day_number, 'activity', null,
                $day->title ?: ('Day ' . $day->day_number), null, 'both', 1, $day->description);

            // Overnight stay as a check-in marker if we resolved a hotel.
            if ($hotel) {
                $this->makeEvent($trip, $tripDay, $day->day_number, 'checkin', null,
                    'Overnight: ' . $hotel, $hotel, 'both', 2);
            }
        }

        // Departure marker on the last day.
        $last = $itineraries->max('day_number');
        $lastDay = TripDay::where('trip_id', $trip->id)->where('day_number', $last)->first();
        $this->makeEvent($trip, $lastDay, $last, 'departure', null, 'Departure', $trip->destination_label, 'both', 9);
    }

    protected function buildHotelPlan(Trip $trip, Booking $booking): void
    {
        $hb = $booking->hotelBooking;
        $in = $hb?->check_in ? Carbon::parse($hb->check_in) : Carbon::today();
        $out = $hb?->check_out ? Carbon::parse($hb->check_out) : $in->copy()->addDay();
        $nights = max(1, $in->diffInDays($out));

        for ($i = 0; $i <= $nights; $i++) {
            $date = $in->copy()->addDays($i);
            $dayNo = $i + 1;
            $title = $i === 0 ? 'Check-in — ' . ($hb?->hotel_name ?? 'Hotel')
                : ($i === $nights ? 'Check-out' : 'Stay at ' . ($hb?->hotel_name ?? 'Hotel'));
            $day = $this->makeDay($trip, $dayNo, $date, $title, null, $hb?->hotel_name, null);

            if ($i === 0) {
                $this->makeEvent($trip, $day, $dayNo, 'checkin', null,
                    'Check-in: ' . ($hb?->hotel_name ?? 'Hotel'), $hb?->hotel_name, 'both');
            } elseif ($i === $nights) {
                $this->makeEvent($trip, $day, $dayNo, 'checkout', null, 'Check-out', $hb?->hotel_name, 'both');
            }
        }
    }

    protected function buildCabPlan(Trip $trip, Booking $booking): void
    {
        $cab = $booking->cab;
        $pickup = $cab?->pickup_datetime ? Carbon::parse($cab->pickup_datetime) : Carbon::today();
        $day = $this->makeDay($trip, 1, $pickup->copy()->startOfDay(),
            'Transfer: ' . ($cab?->pickup_location ?? '') . ' → ' . ($cab?->drop_location ?? ''),
            null, null, null);

        $this->makeEvent($trip, $day, 1, 'transfer', $pickup->format('H:i:s'),
            trim(($cab?->pickup_location ?? 'Pickup') . ' → ' . ($cab?->drop_location ?? 'Drop')),
            $cab?->pickup_location, 'both', 0,
            'Vehicle: ' . ($cab?->vehicle_name ?? '—'));
    }

    protected function buildFlightPlan(Trip $trip, Booking $booking): void
    {
        $flight = $booking->flight;
        $journey = $flight?->journey ?? [];
        $first = $journey[0] ?? ($journey['segments'][0] ?? null);
        $departAt = isset($first['departure_at']) ? Carbon::parse($first['departure_at']) : Carbon::today();
        $day = $this->makeDay($trip, 1, $departAt->copy()->startOfDay(), 'Flight', null, null, null);

        $route = trim(($first['origin'] ?? '') . ' → ' . ($first['destination'] ?? ''), ' →');
        $this->makeEvent($trip, $day, 1, 'departure', $departAt->format('H:i:s'),
            'Flight ' . ($flight?->flight_number ?? '') . ($route ? " ({$route})" : ''),
            $first['origin'] ?? null, 'both', 0);
    }

    protected function buildGenericPlan(Trip $trip, Booking $booking): void
    {
        $day = $this->makeDay($trip, 1, $trip->arrival_date ?? Carbon::today(), 'Trip', null, null, null);
        $this->makeEvent($trip, $day, 1, 'custom', null, 'Trip day', null, 'both');
    }

    /* ============================ helpers ============================ */

    protected function makeDay(Trip $trip, int $number, ?Carbon $date, ?string $title, ?string $summary, ?string $hotel, ?string $meals = null): TripDay
    {
        return TripDay::create([
            'trip_id' => $trip->id,
            'day_number' => $number,
            'date' => $date,
            'title' => $title,
            'summary' => $summary,
            'hotel_snapshot' => $hotel,
            'meals' => $meals,
            'sort_order' => $number,
        ]);
    }

    protected function makeEvent(Trip $trip, ?TripDay $day, int $dayNumber, string $type, ?string $time, string $title, ?string $location, string $visibility, int $sort = 0, ?string $description = null): TripEvent
    {
        return TripEvent::create([
            'trip_id' => $trip->id,
            'trip_day_id' => $day?->id,
            'day_number' => $dayNumber,
            'event_type' => $type,
            'time' => $time,
            'title' => $title,
            'location' => $location,
            'description' => $description,
            'visibility' => $visibility,
            'sort_order' => $sort,
        ]);
    }

    /** Derive [arrival, departure, totalDays, destinationLabel] from the booking. */
    protected function deriveWindow(Booking $booking): array
    {
        return match ($booking->product_type) {
            'package' => $this->packageWindow($booking),
            'hotel' => $this->hotelWindow($booking),
            'cab' => $this->cabWindow($booking),
            'flight' => $this->flightWindow($booking),
            default => [Carbon::today(), Carbon::today(), 1, null],
        };
    }

    protected function packageWindow(Booking $booking): array
    {
        $pb = $booking->packageBooking;
        $package = $pb?->package;
        $start = $pb?->departure_date ? Carbon::parse($pb->departure_date) : Carbon::today();
        $days = (int) ($package?->duration_days ?: ($package?->itineraries?->max('day_number') ?: 1));
        $days = max(1, $days);
        $end = $start->copy()->addDays($days - 1);
        $destination = $package?->destination?->name ?? $package?->name;

        return [$start, $end, $days, $destination];
    }

    protected function hotelWindow(Booking $booking): array
    {
        $hb = $booking->hotelBooking;
        $in = $hb?->check_in ? Carbon::parse($hb->check_in) : Carbon::today();
        $out = $hb?->check_out ? Carbon::parse($hb->check_out) : $in->copy()->addDay();
        $days = max(1, $in->diffInDays($out) + 1);

        return [$in, $out, $days, $hb?->hotel_name];
    }

    protected function cabWindow(Booking $booking): array
    {
        $cab = $booking->cab;
        $date = $cab?->pickup_datetime ? Carbon::parse($cab->pickup_datetime) : Carbon::today();

        return [$date->copy()->startOfDay(), $date->copy()->startOfDay(), 1,
            trim(($cab?->pickup_location ?? '') . ' → ' . ($cab?->drop_location ?? ''), ' →') ?: null];
    }

    protected function flightWindow(Booking $booking): array
    {
        $flight = $booking->flight;
        $journey = $flight?->journey ?? [];
        $first = $journey[0] ?? ($journey['segments'][0] ?? null);
        $date = isset($first['departure_at']) ? Carbon::parse($first['departure_at']) : Carbon::today();

        return [$date->copy()->startOfDay(), $date->copy()->startOfDay(), 1,
            $first['destination'] ?? null];
    }

    protected function makeReference(Booking $booking): string
    {
        return 'TRP-' . strtoupper(Str::of($booking->booking_reference)->replace('BK-', '')->limit(10, '')) . '-' . $booking->id;
    }
}

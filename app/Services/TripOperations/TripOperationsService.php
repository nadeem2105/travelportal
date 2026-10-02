<?php

namespace App\Services\TripOperations;

use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Support\Carbon;

/**
 * Read/aggregate operations over trips: status synchronisation, the "Today" dashboard
 * KPIs, and the queues that power Upcoming / Active / Completed lists.
 */
class TripOperationsService
{
    public function __construct(protected TripBuilder $builder) {}

    /**
     * Ensure a trip exists for a confirmed booking and (optionally) build its itinerary.
     * Safe to call repeatedly (idempotent).
     */
    public function syncFromBooking(Booking $booking, bool $generate = true): ?Trip
    {
        // Only operationally-relevant bookings become trips.
        if (! in_array($booking->product_type, ['package', 'hotel', 'cab', 'flight'], true)) {
            return null;
        }

        $trip = $this->builder->ensureTrip($booking);

        if ($generate && ! $trip->itinerary_generated) {
            $trip = $this->builder->generateItinerary($trip);
        }

        $this->refreshStatus($trip);

        return $trip;
    }

    /** Recompute and persist the operational status from booking + dates. */
    public function refreshStatus(Trip $trip): Trip
    {
        $derived = $trip->deriveStatus();
        if ($derived !== $trip->status) {
            $trip->forceFill(['status' => $derived]);
            if ($derived === Trip::STATUS_IN_PROGRESS && ! $trip->started_at) {
                $trip->started_at = now();
            }
            if ($derived === Trip::STATUS_COMPLETED && ! $trip->completed_at) {
                $trip->completed_at = now();
            }
            $trip->save();
        }

        return $trip;
    }

    /** Bulk status refresh — used by the scheduler and dashboard load. */
    public function refreshAllActiveStatuses(): int
    {
        $count = 0;
        Trip::with('booking')
            ->whereNotIn('status', [Trip::STATUS_COMPLETED, Trip::STATUS_CANCELLED])
            ->chunkById(200, function ($trips) use (&$count) {
                foreach ($trips as $trip) {
                    $before = $trip->status;
                    $this->refreshStatus($trip);
                    if ($trip->status !== $before) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    /* ============================ dashboards ============================ */

    public function todayKpis(): array
    {
        $today = Carbon::today();

        return [
            'arrivals_today' => Trip::whereDate('arrival_date', $today)
                ->where('status', '!=', Trip::STATUS_CANCELLED)->count(),
            'departures_today' => Trip::whereDate('departure_date', $today)
                ->where('status', '!=', Trip::STATUS_CANCELLED)->count(),
            'active_trips' => Trip::whereIn('status', [Trip::STATUS_ARRIVING_TODAY, Trip::STATUS_IN_PROGRESS])->count(),
            'upcoming_7d' => Trip::whereBetween('arrival_date', [$today, $today->copy()->addDays(7)])
                ->whereIn('status', [Trip::STATUS_UPCOMING, Trip::STATUS_ARRIVING_TODAY])->count(),
            'unassigned' => Trip::whereIn('status', [Trip::STATUS_UPCOMING, Trip::STATUS_ARRIVING_TODAY, Trip::STATUS_IN_PROGRESS])
                ->whereDoesntHave('activeAssignments')->count(),
        ];
    }

    public function arrivalsToday()
    {
        return Trip::with(['booking', 'activeAssignments.driver'])
            ->whereDate('arrival_date', Carbon::today())
            ->where('status', '!=', Trip::STATUS_CANCELLED)
            ->orderBy('arrival_date')
            ->get();
    }

    /** Live timeline: events happening today across all in-progress / arriving trips. */
    public function todayTimeline()
    {
        $today = Carbon::today();

        return Trip::with(['events' => function ($q) use ($today) {
            $q->whereHas('day', fn ($d) => $d->whereDate('date', $today));
        }, 'booking'])
            ->whereIn('status', [Trip::STATUS_ARRIVING_TODAY, Trip::STATUS_IN_PROGRESS])
            ->get()
            ->filter(fn ($t) => $t->events->isNotEmpty());
    }
}

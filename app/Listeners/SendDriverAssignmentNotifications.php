<?php

namespace App\Listeners;

use App\Events\DriverAssigned;
use App\Services\TripOperations\TripCommService;
use Illuminate\Support\Facades\Log;

/**
 * Sends driver-assignment notifications (driver + implicitly the customer itinerary)
 * when a driver is assigned/reassigned. Runs SYNCHRONOUSLY so the driver's WhatsApp/SMS
 * goes out immediately as part of the assign request (the admin explicitly wants the
 * message sent the moment a driver is assigned). All sends are idempotent inside
 * TripCommService, and the customer itinerary email itself is a queued Mailable, so
 * the only inline cost is the driver's WhatsApp/SMS round-trip.
 */
class SendDriverAssignmentNotifications
{
    public function __construct(protected TripCommService $comms) {}

    public function handle(DriverAssigned $event): void
    {
        $assignment = $event->assignment;

        try {
            // Notify the assigned driver (WhatsApp template / SMS, guarded).
            $this->comms->sendDriverAssignment($assignment);

            // Ensure the customer has their itinerary once a driver is confirmed.
            if ($trip = $assignment->trip) {
                $this->comms->sendCustomerItinerary($trip);
            }
        } catch (\Throwable $e) {
            Log::warning('SendDriverAssignmentNotifications failed', [
                'assignment_id' => $assignment->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

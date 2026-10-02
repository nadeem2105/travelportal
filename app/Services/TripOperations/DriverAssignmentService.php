<?php

namespace App\Services\TripOperations;

use App\Events\DriverAssigned;
use App\Models\Driver;
use App\Models\DriverAssignment;
use App\Models\DriverAssignmentHistory;
use App\Models\Trip;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Creates and reassigns driver assignments with a full audit trail, and fires the
 * DriverAssigned event so customer + driver notifications go out (idempotently).
 */
class DriverAssignmentService
{
    /**
     * Assign a driver to a trip (entire trip / a specific day / a single transfer).
     */
    public function assign(Trip $trip, Driver $driver, array $data = []): DriverAssignment
    {
        $assignment = DB::transaction(function () use ($trip, $driver, $data) {
            $assignment = DriverAssignment::create([
                'trip_id' => $trip->id,
                'driver_id' => $driver->id,
                'scope' => $data['scope'] ?? DriverAssignment::SCOPE_ENTIRE,
                'trip_day_id' => $data['trip_day_id'] ?? null,
                'trip_event_id' => $data['trip_event_id'] ?? null,
                'pickup_location' => $data['pickup_location'] ?? null,
                'drop_location' => $data['drop_location'] ?? null,
                'pickup_datetime' => $data['pickup_datetime'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'assigned',
                'assigned_by' => auth('admin')->id(),
                'assigned_at' => now(),
            ]);

            DriverAssignmentHistory::create([
                'driver_assignment_id' => $assignment->id,
                'from_driver_id' => null,
                'to_driver_id' => $driver->id,
                'reason' => $data['reason'] ?? 'Initial assignment',
                'changed_by' => auth('admin')->id(),
            ]);

            return $assignment;
        });

        ActivityLogger::log('assign', 'trip_operations',
            "Assigned driver {$driver->name} to trip {$trip->trip_reference} ({$assignment->scopeLabel()})",
            ['assignment_id' => $assignment->id]);

        // Fire notifications (customer + driver), unless caller opts out.
        if ($data['notify'] ?? true) {
            event(new DriverAssigned($assignment->fresh(['trip.booking', 'driver'])));
        }

        return $assignment;
    }

    /**
     * Reassign an existing assignment to a different driver, keeping history.
     */
    public function reassign(DriverAssignment $assignment, Driver $newDriver, ?string $reason = null): DriverAssignment
    {
        $oldDriverId = $assignment->driver_id;
        if ($oldDriverId === $newDriver->id) {
            return $assignment;
        }

        DB::transaction(function () use ($assignment, $newDriver, $oldDriverId, $reason) {
            DriverAssignmentHistory::create([
                'driver_assignment_id' => $assignment->id,
                'from_driver_id' => $oldDriverId,
                'to_driver_id' => $newDriver->id,
                'reason' => $reason ?: 'Reassigned',
                'changed_by' => auth('admin')->id(),
            ]);

            $assignment->forceFill([
                'driver_id' => $newDriver->id,
                'status' => 'reassigned',
                'assigned_by' => auth('admin')->id(),
                'assigned_at' => now(),
            ])->save();
        });

        ActivityLogger::log('reassign', 'trip_operations',
            "Reassigned trip {$assignment->trip->trip_reference} to driver {$newDriver->name}",
            ['assignment_id' => $assignment->id, 'from_driver_id' => $oldDriverId]);

        // New driver + customer should be (re)notified; idempotency key includes the driver.
        event(new DriverAssigned($assignment->fresh(['trip.booking', 'driver']), true));

        return $assignment;
    }

    public function cancel(DriverAssignment $assignment, ?string $reason = null): void
    {
        $assignment->forceFill(['status' => 'cancelled'])->save();
        DriverAssignmentHistory::create([
            'driver_assignment_id' => $assignment->id,
            'from_driver_id' => $assignment->driver_id,
            'to_driver_id' => null,
            'reason' => $reason ?: 'Cancelled',
            'changed_by' => auth('admin')->id(),
        ]);
        ActivityLogger::log('cancel', 'trip_operations',
            "Cancelled driver assignment #{$assignment->id} on trip {$assignment->trip->trip_reference}");
    }
}

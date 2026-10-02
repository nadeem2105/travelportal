<?php

namespace App\Events;

use App\Models\DriverAssignment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a driver is assigned (or reassigned) to a trip. The listener sends
 * the driver + customer operational notifications idempotently. Carries the
 * assignment (eager-loaded with trip.booking + driver) and whether this was a
 * reassignment (so wording / audit can differ downstream if needed).
 */
class DriverAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DriverAssignment $assignment,
        public bool $isReassign = false,
    ) {}
}

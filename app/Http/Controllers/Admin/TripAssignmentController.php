<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverAssignment;
use App\Models\Trip;
use App\Services\TripOperations\DriverAssignmentService;
use Illuminate\Http\Request;

/**
 * Driver assignment actions for a trip: assign (entire trip / day / transfer),
 * reassign (keeps history) and cancel. Firing the DriverAssigned event (inside the
 * service) drives idempotent driver + customer notifications.
 */
class TripAssignmentController extends Controller
{
    public function __construct(protected DriverAssignmentService $assignments) {}

    /** Global driver-assignment queue (audit / overview). */
    public function index(Request $request)
    {
        $rows = DriverAssignment::query()
            ->with(['trip', 'driver'])
            ->whereIn('status', ['assigned', 'reassigned'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->query('q') . '%';
                $q->whereHas('trip', fn ($t) => $t->where('trip_reference', 'like', $term)
                    ->orWhere('lead_customer_name', 'like', $term))
                    ->orWhereHas('driver', fn ($d) => $d->where('name', 'like', $term));
            })
            ->orderByDesc('assigned_at')
            ->paginate((int) $request->query('per_page', 20))
            ->withQueryString();

        return view('admin.trip-ops.assignments', [
            'assignments' => $rows,
            'drivers' => Driver::active()->orderBy('name')->get(),
            'filters' => $request->only(['q']),
        ]);
    }

    public function store(Request $request, Trip $trip)
    {
        $data = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'scope' => 'required|in:entire_trip,day,transfer',
            'trip_day_id' => 'nullable|exists:trip_days,id',
            'trip_event_id' => 'nullable|exists:trip_events,id',
            'pickup_location' => 'nullable|string|max:255',
            'drop_location' => 'nullable|string|max:255',
            'pickup_datetime' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'reason' => 'nullable|string|max:255',
            'notify' => 'nullable|boolean',
        ]);

        $driver = Driver::findOrFail($data['driver_id']);
        $this->assignments->assign($trip, $driver, $data);

        return back()->with('success', "Driver {$driver->name} assigned and notified immediately.");
    }

    public function reassign(Request $request, DriverAssignment $assignment)
    {
        $data = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'reason' => 'nullable|string|max:255',
        ]);

        $driver = Driver::findOrFail($data['driver_id']);
        $this->assignments->reassign($assignment, $driver, $data['reason'] ?? null);

        return back()->with('success', "Reassigned to {$driver->name}.");
    }

    public function cancel(Request $request, DriverAssignment $assignment)
    {
        $this->assignments->cancel($assignment, $request->input('reason'));

        return back()->with('success', 'Assignment cancelled.');
    }
}

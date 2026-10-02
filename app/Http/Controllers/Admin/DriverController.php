<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

/**
 * Driver directory (net-new — no drivers table existed before Trip Operations).
 * Reusable driver profiles referenced by driver assignments.
 */
class DriverController extends Controller
{
    public function index(Request $request)
    {
        $drivers = Driver::query()
            ->withCount('activeAssignments')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->query('q') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('vehicle_number', 'like', $term)
                    ->orWhere('vendor_name', 'like', $term));
            })
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 20))
            ->withQueryString();

        return view('admin.trip-ops.drivers.index', [
            'drivers' => $drivers,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateDriver($request);
        $driver = Driver::create($data);

        ActivityLogger::log('create', 'trip_operations', "Added driver {$driver->name}", ['driver_id' => $driver->id]);

        return back()->with('success', "Driver {$driver->name} added.");
    }

    public function update(Request $request, Driver $driver)
    {
        $driver->update($this->validateDriver($request, $driver));

        ActivityLogger::log('update', 'trip_operations', "Updated driver {$driver->name}", ['driver_id' => $driver->id]);

        return back()->with('success', 'Driver updated.');
    }

    public function toggle(Driver $driver)
    {
        $driver->update(['is_active' => ! $driver->is_active]);

        return back()->with('success', $driver->is_active ? 'Driver activated.' : 'Driver deactivated.');
    }

    public function destroy(Driver $driver)
    {
        if ($driver->activeAssignments()->exists()) {
            return back()->with('error', 'Cannot delete a driver with active assignments. Deactivate instead.');
        }
        $name = $driver->name;
        $driver->delete();

        ActivityLogger::log('delete', 'trip_operations', "Deleted driver {$name}");

        return back()->with('success', 'Driver deleted.');
    }

    protected function validateDriver(Request $request, ?Driver $driver = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'required|string|max:30',
            'alt_phone' => 'nullable|string|max:30',
            'license_number' => 'nullable|string|max:60',
            'vehicle_number' => 'nullable|string|max:30',
            'vehicle_type' => 'nullable|string|max:40',
            'vehicle_model' => 'nullable|string|max:80',
            'vendor_name' => 'nullable|string|max:120',
            'home_base' => 'nullable|string|max:120',
            'rating' => 'nullable|numeric|min:0|max:5',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);
    }
}

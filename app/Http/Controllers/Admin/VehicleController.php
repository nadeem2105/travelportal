<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $sortable = array_merge(config('crud_fields.vehicles')['columns'], ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $vehicles = Vehicle::query()
            ->with('vendor', 'type')
            ->when($request->input('q'), fn ($query, $v) => $query->where('name', 'like', "%{$v}%"))
            ->when($request->input('vehicle_type_id'), fn ($query, $v) => $query->where('vehicle_type_id', $v))
            ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->latest())
            ->paginate($perPage)
            ->withQueryString();

        $vehicleTypes = VehicleType::orderBy('name')->get();

        return view('admin.vehicles.index', compact('vehicles', 'vehicleTypes'));
    }

    public function export(Request $request): StreamedResponse
    {
        $columns = config('crud_fields.vehicles')['columns'];
        $sortable = array_merge($columns, ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'vehicles-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'vehicle', 'Exported vehicles CSV');

        return response()->streamDownload(function () use ($request, $columns, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['id'], $columns));

            Vehicle::query()
                ->with('vendor', 'type')
                ->when($request->input('q'), fn ($query, $v) => $query->where('name', 'like', "%{$v}%"))
                ->when($request->input('vehicle_type_id'), fn ($query, $v) => $query->where('vehicle_type_id', $v))
                ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
                ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
                ->when(! $applySort, fn ($query) => $query->latest())
                ->chunk(500, fn ($rows) => $this->writeCsvRows($out, $rows, $columns));

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function writeCsvRows($out, $rows, array $columns): void
    {
        foreach ($rows as $row) {
            $line = [$row->id];
            foreach ($columns as $col) {
                $val = $row->{$col};
                if (is_array($val)) {
                    $val = implode(', ', $val);
                } elseif (is_bool($val)) {
                    $val = $val ? '1' : '0';
                } elseif ($val instanceof \Carbon\CarbonInterface) {
                    $val = $val->toDateTimeString();
                }
                $line[] = $val;
            }
            fputcsv($out, $line);
        }
    }

    public function create()
    {
        return view('admin.vehicles.form', ['vehicle' => new Vehicle()]);
    }

    public function store(Request $request)
    {
        Vehicle::create($this->validated($request, null));

        ActivityLogger::log('create', 'vehicle', 'Created vehicle entry');

        return redirect()->route('admin.vehicles.index')->with('success', 'Vehicle created.');
    }

    public function edit(Vehicle $vehicle)
    {
        return view('admin.vehicles.form', ['vehicle' => $vehicle]);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $vehicle->update($this->validated($request, $vehicle));

        ActivityLogger::log('update', 'vehicle', 'Updated vehicle entry #' . $vehicle->id);

        return back()->with('success', 'Vehicle updated.');
    }

    public function destroy(Vehicle $vehicle)
    {
        ActivityLogger::log('delete', 'vehicle', 'Deleted vehicle entry #' . $vehicle->id);
        $vehicle->delete();

        return back()->with('success', 'Vehicle deleted.');
    }

    protected function validated(Request $request, ?Vehicle $model): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'vendor_id' => 'nullable|integer|exists:cab_vendors,id',
            'vehicle_type_id' => 'required|integer|exists:vehicle_types,id',
            'image' => 'nullable|string|max:255',
            'passenger_capacity' => 'required|integer|min:1|max:50',
            'luggage_capacity' => 'required|integer|min:0|max:20',
            'base_price' => 'required|numeric|min:0',
            'per_km_rate' => 'required|numeric|min:0',
            'per_hour_rate' => 'nullable|numeric|min:0',
            'cancellation_policy' => 'nullable|string|max:1000',
            'status' => 'required|in:active,inactive',
        ]);
        $validated['is_ac'] = $request->boolean('is_ac');
        $validated['is_featured'] = $request->boolean('is_featured');

        return $validated;
    }
}
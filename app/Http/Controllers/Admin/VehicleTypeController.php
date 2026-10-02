<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VehicleType;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleTypeController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $sortable = array_merge(config('crud_fields.vehicle_types')['columns'], ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $vehicleTypes = VehicleType::query()
            ->when($request->input('q'), fn ($query, $v) => $query->where('name', 'like', "%{$v}%"))
            ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->when($request->input('sort') === 'oldest', fn ($q) => $q->oldest(), fn ($q) => $q->latest()))
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.vehicle_types.index', compact('vehicleTypes'));
    }

    public function export(Request $request): StreamedResponse
    {
        $columns = config('crud_fields.vehicle_types')['columns'];
        $sortable = array_merge($columns, ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'vehicle_types-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'vehicle_type', 'Exported vehicle_types CSV');

        return response()->streamDownload(function () use ($request, $columns, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['id'], $columns));

            VehicleType::query()
                ->when($request->input('q'), fn ($query, $v) => $query->where('name', 'like', "%{$v}%"))
                ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
                ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
                ->when(! $applySort, fn ($query) => $query->when($request->input('sort') === 'oldest', fn ($q) => $q->oldest(), fn ($q) => $q->latest()))
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
        return view('admin.vehicle_types.form', ['vehicleType' => new VehicleType()]);
    }

    public function store(Request $request)
    {
        VehicleType::create($this->validated($request, null));

        ActivityLogger::log('create', 'vehicle_type', 'Created vehicle_type entry');

        return redirect()->route('admin.vehicle_types.index')->with('success', 'Vehicle Type created.');
    }

    public function edit(VehicleType $vehicleType)
    {
        return view('admin.vehicle_types.form', ['vehicleType' => $vehicleType]);
    }

    public function update(Request $request, VehicleType $vehicleType)
    {
        $vehicleType->update($this->validated($request, $vehicleType));

        ActivityLogger::log('update', 'vehicle_type', 'Updated vehicle_type entry #' . $vehicleType->id);

        return back()->with('success', 'Vehicle Type updated.');
    }

    public function destroy(VehicleType $vehicleType)
    {
        ActivityLogger::log('delete', 'vehicle_type', 'Deleted vehicle_type entry #' . $vehicleType->id);
        $vehicleType->delete();

        return back()->with('success', 'Vehicle Type deleted.');
    }

    protected function validated(Request $request, ?VehicleType $model): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive',
        ]);

        return $validated;
    }
}
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CabLocation;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CabLocationController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $sortable = array_merge(config('crud_fields.cab_locations')['columns'], ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $cabLocations = CabLocation::query()
            ->when($request->input('q'), fn ($query, $v) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$v}%")
                ->orWhere('city', 'like', "%{$v}%")))
            ->when($request->input('type'), fn ($query, $v) => $query->where('type', $v))
            ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->when($request->input('sort') === 'oldest', fn ($q) => $q->oldest(), fn ($q) => $q->latest()))
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.cab_locations.index', compact('cabLocations'));
    }

    public function export(Request $request): StreamedResponse
    {
        $columns = config('crud_fields.cab_locations')['columns'];
        $sortable = array_merge($columns, ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'cab_locations-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'cab_location', 'Exported cab_locations CSV');

        return response()->streamDownload(function () use ($request, $columns, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['id'], $columns));

            CabLocation::query()
                ->when($request->input('q'), fn ($query, $v) => $query->where(fn ($w) => $w
                    ->where('name', 'like', "%{$v}%")
                    ->orWhere('city', 'like', "%{$v}%")))
                ->when($request->input('type'), fn ($query, $v) => $query->where('type', $v))
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
        return view('admin.cab_locations.form', ['cabLocation' => new CabLocation()]);
    }

    public function store(Request $request)
    {
        CabLocation::create($this->validated($request, null));

        ActivityLogger::log('create', 'cab_location', 'Created cab_location entry');

        return redirect()->route('admin.cab_locations.index')->with('success', 'Cab Location created.');
    }

    public function edit(CabLocation $cabLocation)
    {
        return view('admin.cab_locations.form', ['cabLocation' => $cabLocation]);
    }

    public function update(Request $request, CabLocation $cabLocation)
    {
        $cabLocation->update($this->validated($request, $cabLocation));

        ActivityLogger::log('update', 'cab_location', 'Updated cab_location entry #' . $cabLocation->id);

        return back()->with('success', 'Cab Location updated.');
    }

    public function destroy(CabLocation $cabLocation)
    {
        ActivityLogger::log('delete', 'cab_location', 'Deleted cab_location entry #' . $cabLocation->id);
        $cabLocation->delete();

        return back()->with('success', 'Cab Location deleted.');
    }

    protected function validated(Request $request, ?CabLocation $model): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'city' => 'nullable|string|max:80',
            'type' => 'required|in:airport,local,outstation',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive',
        ]);

        return $validated;
    }
}
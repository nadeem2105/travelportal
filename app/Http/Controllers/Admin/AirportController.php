<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Airport;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AirportController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $sortable = array_merge(config('crud_fields.airports')['columns'], ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $airports = Airport::query()
            ->when($request->input('q'), fn ($query, $v) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$v}%")
                    ->orWhere('code', 'like', "%{$v}%")
                    ->orWhere('city', 'like', "%{$v}%")
            ))
            ->when($request->input('country'), fn ($query, $v) => $query->where('country', $v))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->latest())
            ->paginate($perPage)
            ->withQueryString();

        $countries = Airport::query()->whereNotNull('country')->where('country', '!=', '')
            ->distinct()->orderBy('country')->pluck('country');

        return view('admin.airports.index', compact('airports', 'countries'));
    }

    public function export(Request $request): StreamedResponse
    {
        $columns = config('crud_fields.airports')['columns'];
        $sortable = array_merge($columns, ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'airports-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'airport', 'Exported airports CSV');

        return response()->streamDownload(function () use ($request, $columns, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['id'], $columns));

            Airport::query()
                ->when($request->input('q'), fn ($query, $v) => $query->where(
                    fn ($q) => $q->where('name', 'like', "%{$v}%")
                        ->orWhere('code', 'like', "%{$v}%")
                        ->orWhere('city', 'like', "%{$v}%")
                ))
                ->when($request->input('country'), fn ($query, $v) => $query->where('country', $v))
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
        return view('admin.airports.form', ['airport' => new Airport()]);
    }

    public function store(Request $request)
    {
        Airport::create($this->validated($request, null));

        ActivityLogger::log('create', 'airport', 'Created airport entry');

        return redirect()->route('admin.airports.index')->with('success', 'Airport created.');
    }

    public function edit(Airport $airport)
    {
        return view('admin.airports.form', ['airport' => $airport]);
    }

    public function update(Request $request, Airport $airport)
    {
        $airport->update($this->validated($request, $airport));

        ActivityLogger::log('update', 'airport', 'Updated airport entry #' . $airport->id);

        return back()->with('success', 'Airport updated.');
    }

    public function destroy(Airport $airport)
    {
        ActivityLogger::log('delete', 'airport', 'Deleted airport entry #' . $airport->id);
        $airport->delete();

        return back()->with('success', 'Airport deleted.');
    }

    protected function validated(Request $request, ?Airport $model): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:3', \Illuminate\Validation\Rule::unique('airports', 'code')->ignore($model?->id)],
            'name' => 'required|string|max:150',
            'city' => 'required|string|max:80',
            'country' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive',
        ]);
        $validated['is_popular'] = $request->boolean('is_popular');

        return $validated;
    }
}
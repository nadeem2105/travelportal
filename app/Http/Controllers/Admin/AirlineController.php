<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Airline;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AirlineController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $sortable = array_merge(config('crud_fields.airlines')['columns'], ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $airlines = Airline::query()
            ->when($request->input('q'), fn ($query, $v) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$v}%")->orWhere('code', 'like', "%{$v}%")
            ))
            ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->latest())
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.airlines.index', compact('airlines'));
    }

    public function export(Request $request): StreamedResponse
    {
        $columns = config('crud_fields.airlines')['columns'];
        $sortable = array_merge($columns, ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'airlines-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'airline', 'Exported airlines CSV');

        return response()->streamDownload(function () use ($request, $columns, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['id'], $columns));

            Airline::query()
                ->when($request->input('q'), fn ($query, $v) => $query->where(
                    fn ($q) => $q->where('name', 'like', "%{$v}%")->orWhere('code', 'like', "%{$v}%")
                ))
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
        return view('admin.airlines.form', ['airline' => new Airline()]);
    }

    public function store(Request $request)
    {
        Airline::create($this->validated($request, null));

        ActivityLogger::log('create', 'airline', 'Created airline entry');

        return redirect()->route('admin.airlines.index')->with('success', 'Airline created.');
    }

    public function edit(Airline $airline)
    {
        return view('admin.airlines.form', ['airline' => $airline]);
    }

    public function update(Request $request, Airline $airline)
    {
        $airline->update($this->validated($request, $airline));

        ActivityLogger::log('update', 'airline', 'Updated airline entry #' . $airline->id);

        return back()->with('success', 'Airline updated.');
    }

    public function destroy(Airline $airline)
    {
        ActivityLogger::log('delete', 'airline', 'Deleted airline entry #' . $airline->id);
        $airline->delete();

        return back()->with('success', 'Airline deleted.');
    }

    protected function validated(Request $request, ?Airline $model): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:2', \Illuminate\Validation\Rule::unique('airlines', 'code')->ignore($model?->id)],
            'name' => 'required|string|max:100',
            'default_baggage_kg' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,inactive',
        ]);
        $validated['is_lcc'] = $request->boolean('is_lcc');

        return $validated;
    }
}
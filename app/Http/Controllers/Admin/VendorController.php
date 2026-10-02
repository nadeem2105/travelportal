<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CabVendor;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $sortable = array_merge(config('crud_fields.vendors')['columns'], ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $vendors = CabVendor::query()
            ->when($request->input('q'), fn ($query, $v) => $query->where('name', 'like', "%{$v}%"))
            ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->latest())
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.vendors.index', compact('vendors'));
    }

    public function export(Request $request): StreamedResponse
    {
        $columns = config('crud_fields.vendors')['columns'];
        $sortable = array_merge($columns, ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'vendors-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'vendor', 'Exported vendors CSV');

        return response()->streamDownload(function () use ($request, $columns, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['id'], $columns));

            CabVendor::query()
                ->when($request->input('q'), fn ($query, $v) => $query->where('name', 'like', "%{$v}%"))
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
        return view('admin.vendors.form', ['vendor' => new CabVendor()]);
    }

    public function store(Request $request)
    {
        CabVendor::create($this->validated($request, null));

        ActivityLogger::log('create', 'vendor', 'Created vendor entry');

        return redirect()->route('admin.vendors.index')->with('success', 'Vendor created.');
    }

    public function edit(CabVendor $vendor)
    {
        return view('admin.vendors.form', ['vendor' => $vendor]);
    }

    public function update(Request $request, CabVendor $vendor)
    {
        $vendor->update($this->validated($request, $vendor));

        ActivityLogger::log('update', 'vendor', 'Updated vendor entry #' . $vendor->id);

        return back()->with('success', 'Vendor updated.');
    }

    public function destroy(CabVendor $vendor)
    {
        ActivityLogger::log('delete', 'vendor', 'Deleted vendor entry #' . $vendor->id);
        $vendor->delete();

        return back()->with('success', 'Vendor deleted.');
    }

    protected function validated(Request $request, ?CabVendor $model): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'commission_percent' => 'nullable|numeric|min:0|max:100',
            'status' => 'required|in:active,inactive',
        ]);

        return $validated;
    }
}
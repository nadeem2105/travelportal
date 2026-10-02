<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guide;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuideController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $sortable = array_merge(config('crud_fields.guides')['columns'], ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $guides = Guide::query()
            ->when($request->input('q'), fn ($query, $v) => $query->where('title', 'like', "%{$v}%"))
            ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->latest())
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.guides.index', compact('guides'));
    }

    public function export(Request $request): StreamedResponse
    {
        $columns = config('crud_fields.guides')['columns'];
        $sortable = array_merge($columns, ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'guides-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'guide', 'Exported guides CSV');

        return response()->streamDownload(function () use ($request, $columns, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['id'], $columns));

            Guide::query()
                ->when($request->input('q'), fn ($query, $v) => $query->where('title', 'like', "%{$v}%"))
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
        return view('admin.guides.form', ['guide' => new Guide()]);
    }

    public function store(Request $request)
    {
        Guide::create($this->validated($request, null));

        ActivityLogger::log('create', 'guide', 'Created guide entry');

        return redirect()->route('admin.guides.index')->with('success', 'Guide created.');
    }

    public function edit(Guide $guide)
    {
        return view('admin.guides.form', ['guide' => $guide]);
    }

    public function update(Request $request, Guide $guide)
    {
        $guide->update($this->validated($request, $guide));

        ActivityLogger::log('update', 'guide', 'Updated guide entry #' . $guide->id);

        return back()->with('success', 'Guide updated.');
    }

    public function destroy(Guide $guide)
    {
        ActivityLogger::log('delete', 'guide', 'Deleted guide entry #' . $guide->id);
        $guide->delete();

        return back()->with('success', 'Guide deleted.');
    }

    protected function validated(Request $request, ?Guide $model): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'destination_id' => 'nullable|integer|exists:destinations,id',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'cover_image' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive',
        ]);
        $slugBase = Str::slug($validated['title']);
        $slug = $slugBase;
        $i = 1;
        while (Guide::where('slug', $slug)->when($model?->id, fn ($q, $id) => $q->where('id', '!=', $id))->exists()) {
            $slug = $slugBase . '-' . ++$i;
        }
        $validated['slug'] = $slug;

        return $validated;
    }
}
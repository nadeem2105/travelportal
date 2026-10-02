<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PageController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $sortable = array_merge(config('crud_fields.pages')['columns'], ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $pages = Page::query()
            ->when($request->input('q'), fn ($query, $v) => $query->where('title', 'like', "%{$v}%"))
            ->when($request->input('template'), fn ($query, $v) => $query->where('template', $v))
            ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->when($request->input('sort') === 'oldest', fn ($q) => $q->oldest(), fn ($q) => $q->latest()))
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.pages.index', compact('pages'));
    }

    public function export(Request $request): StreamedResponse
    {
        $columns = config('crud_fields.pages')['columns'];
        $sortable = array_merge($columns, ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'pages-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'page', 'Exported pages CSV');

        return response()->streamDownload(function () use ($request, $columns, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['id'], $columns));

            Page::query()
                ->when($request->input('q'), fn ($query, $v) => $query->where('title', 'like', "%{$v}%"))
                ->when($request->input('template'), fn ($query, $v) => $query->where('template', $v))
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
        return view('admin.pages.form', ['page' => new Page()]);
    }

    public function store(Request $request)
    {
        Page::create($this->validated($request, null));

        ActivityLogger::log('create', 'page', 'Created page entry');

        return redirect()->route('admin.pages.index')->with('success', 'Page created.');
    }

    public function edit(Page $page)
    {
        return view('admin.pages.form', ['page' => $page]);
    }

    public function update(Request $request, Page $page)
    {
        $page->update($this->validated($request, $page));

        ActivityLogger::log('update', 'page', 'Updated page entry #' . $page->id);

        return back()->with('success', 'Page updated.');
    }

    public function destroy(Page $page)
    {
        ActivityLogger::log('delete', 'page', 'Deleted page entry #' . $page->id);
        $page->delete();

        return back()->with('success', 'Page deleted.');
    }

    protected function validated(Request $request, ?Page $model): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'nullable|string',
            'template' => 'required|in:default,full_width,sidebar',
            'sort_order' => 'nullable|integer|min:0',
            'published_at' => 'nullable|date',
            'status' => 'required|in:draft,published',
        ]);
        $validated['show_in_footer'] = $request->boolean('show_in_footer');

        $slugBase = Str::slug($validated['title']);
        $slug = $slugBase;
        $i = 1;
        while (Page::where('slug', $slug)->when($model?->id, fn ($q, $id) => $q->where('id', '!=', $id))->exists()) {
            $slug = $slugBase . '-' . ++$i;
        }
        $validated['slug'] = $slug;

        return $validated;
    }
}
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $sortable = array_merge(config('crud_fields.blogs')['columns'], ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $blogs = Blog::query()
            ->when($request->input('q'), fn ($query, $v) => $query->where('title', 'like', "%{$v}%"))
            ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->latest())
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.blogs.index', compact('blogs'));
    }

    public function export(Request $request): StreamedResponse
    {
        $columns = config('crud_fields.blogs')['columns'];
        $sortable = array_merge($columns, ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'blogs-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'blog', 'Exported blogs CSV');

        return response()->streamDownload(function () use ($request, $columns, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['id'], $columns));

            Blog::query()
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
        return view('admin.blogs.form', ['blog' => new Blog()]);
    }

    public function store(Request $request)
    {
        Blog::create($this->validated($request, null));

        ActivityLogger::log('create', 'blog', 'Created blog entry');

        return redirect()->route('admin.blogs.index')->with('success', 'Blog created.');
    }

    public function edit(Blog $blog)
    {
        return view('admin.blogs.form', ['blog' => $blog]);
    }

    public function update(Request $request, Blog $blog)
    {
        $blog->update($this->validated($request, $blog));

        ActivityLogger::log('update', 'blog', 'Updated blog entry #' . $blog->id);

        return back()->with('success', 'Blog updated.');
    }

    public function destroy(Blog $blog)
    {
        ActivityLogger::log('delete', 'blog', 'Deleted blog entry #' . $blog->id);
        $blog->delete();

        return back()->with('success', 'Blog deleted.');
    }

    protected function validated(Request $request, ?Blog $model): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'category' => 'required|string|max:60',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'cover_image' => 'nullable|string|max:255',
            'tags' => 'nullable|string|max:255',
            'published_at' => 'nullable|date',
            'status' => 'required|in:draft,published,scheduled,archived',
        ]);
        $validated['tags'] = ! empty($validated['tags'])
            ? array_values(array_filter(array_map('trim', explode(',', $validated['tags']))))
            : null;

        $slugBase = Str::slug($validated['title']);
        $slug = $slugBase;
        $i = 1;
        while (Blog::where('slug', $slug)->when($model?->id, fn ($q, $id) => $q->where('id', '!=', $id))->exists()) {
            $slug = $slugBase . '-' . ++$i;
        }
        $validated['slug'] = $slug;

        return $validated;
    }
}
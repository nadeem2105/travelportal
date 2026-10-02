<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FaqController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $sortable = array_merge(config('crud_fields.faqs')['columns'], ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $faqs = Faq::query()
            ->when($request->input('q'), fn ($query, $v) => $query->where(fn ($w) => $w
                ->where('question', 'like', "%{$v}%")
                ->orWhere('answer', 'like', "%{$v}%")
                ->orWhere('category', 'like', "%{$v}%")))
            ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->when($request->input('sort') === 'oldest', fn ($q) => $q->oldest(), fn ($q) => $q->latest()))
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.faqs.index', compact('faqs'));
    }

    public function export(Request $request): StreamedResponse
    {
        $columns = config('crud_fields.faqs')['columns'];
        $sortable = array_merge($columns, ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'faqs-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'faq', 'Exported faqs CSV');

        return response()->streamDownload(function () use ($request, $columns, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['id'], $columns));

            Faq::query()
                ->when($request->input('q'), fn ($query, $v) => $query->where(fn ($w) => $w
                    ->where('question', 'like', "%{$v}%")
                    ->orWhere('answer', 'like', "%{$v}%")
                    ->orWhere('category', 'like', "%{$v}%")))
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
        return view('admin.faqs.form', ['faq' => new Faq()]);
    }

    public function store(Request $request)
    {
        Faq::create($this->validated($request, null));

        ActivityLogger::log('create', 'faq', 'Created faq entry');

        return redirect()->route('admin.faqs.index')->with('success', 'Faq created.');
    }

    public function edit(Faq $faq)
    {
        return view('admin.faqs.form', ['faq' => $faq]);
    }

    public function update(Request $request, Faq $faq)
    {
        $faq->update($this->validated($request, $faq));

        ActivityLogger::log('update', 'faq', 'Updated faq entry #' . $faq->id);

        return back()->with('success', 'Faq updated.');
    }

    public function destroy(Faq $faq)
    {
        ActivityLogger::log('delete', 'faq', 'Deleted faq entry #' . $faq->id);
        $faq->delete();

        return back()->with('success', 'Faq deleted.');
    }

    protected function validated(Request $request, ?Faq $model): array
    {
        $validated = $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'category' => 'required|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive',
        ]);

        return $validated;
    }
}
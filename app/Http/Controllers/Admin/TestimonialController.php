<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TestimonialController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $sortable = array_merge(config('crud_fields.testimonials')['columns'], ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $testimonials = Testimonial::query()
            ->when($request->input('q'), fn ($query, $v) => $query->where(fn ($w) => $w
                ->where('customer_name', 'like', "%{$v}%")
                ->orWhere('city', 'like', "%{$v}%")
                ->orWhere('destination', 'like', "%{$v}%")))
            ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
            ->when($request->input('rating'), fn ($query, $v) => $query->where('rating', (int) $v))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->when($request->input('sort') === 'oldest', fn ($q) => $q->oldest(), fn ($q) => $q->latest()))
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.testimonials.index', compact('testimonials'));
    }

    public function export(Request $request): StreamedResponse
    {
        $columns = config('crud_fields.testimonials')['columns'];
        $sortable = array_merge($columns, ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'testimonials-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'testimonial', 'Exported testimonials CSV');

        return response()->streamDownload(function () use ($request, $columns, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['id'], $columns));

            Testimonial::query()
                ->when($request->input('q'), fn ($query, $v) => $query->where(fn ($w) => $w
                    ->where('customer_name', 'like', "%{$v}%")
                    ->orWhere('city', 'like', "%{$v}%")
                    ->orWhere('destination', 'like', "%{$v}%")))
                ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
                ->when($request->input('rating'), fn ($query, $v) => $query->where('rating', (int) $v))
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
        return view('admin.testimonials.form', ['testimonial' => new Testimonial()]);
    }

    public function store(Request $request)
    {
        Testimonial::create($this->validated($request, null));

        ActivityLogger::log('create', 'testimonial', 'Created testimonial entry');

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial created.');
    }

    public function edit(Testimonial $testimonial)
    {
        return view('admin.testimonials.form', ['testimonial' => $testimonial]);
    }

    public function update(Request $request, Testimonial $testimonial)
    {
        $testimonial->update($this->validated($request, $testimonial));

        ActivityLogger::log('update', 'testimonial', 'Updated testimonial entry #' . $testimonial->id);

        return back()->with('success', 'Testimonial updated.');
    }

    public function destroy(Testimonial $testimonial)
    {
        ActivityLogger::log('delete', 'testimonial', 'Deleted testimonial entry #' . $testimonial->id);
        $testimonial->delete();

        return back()->with('success', 'Testimonial deleted.');
    }

    protected function validated(Request $request, ?Testimonial $model): array
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:100',
            'customer_photo' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:80',
            'destination' => 'nullable|string|max:100',
            'rating' => 'required|integer|min:1|max:5',
            'content' => 'required|string|max:1000',
            'status' => 'required|in:active,inactive',
        ]);
        $validated['is_featured'] = $request->boolean('is_featured');

        return $validated;
    }
}
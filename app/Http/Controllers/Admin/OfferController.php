<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfferController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $sortable = array_merge(config('crud_fields.offers')['columns'], ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $offers = Offer::query()
            ->when($request->input('q'), fn ($query, $v) => $query->where('title', 'like', "%{$v}%"))
            ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->latest())
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.offers.index', compact('offers'));
    }

    public function export(Request $request): StreamedResponse
    {
        $columns = config('crud_fields.offers')['columns'];
        $sortable = array_merge($columns, ['id']);
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'offers-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'offer', 'Exported offers CSV');

        return response()->streamDownload(function () use ($request, $columns, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['id'], $columns));

            Offer::query()
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
        return view('admin.offers.form', ['offer' => new Offer()]);
    }

    public function store(Request $request)
    {
        Offer::create($this->validated($request, null));

        ActivityLogger::log('create', 'offer', 'Created offer entry');

        return redirect()->route('admin.offers.index')->with('success', 'Offer created.');
    }

    public function edit(Offer $offer)
    {
        return view('admin.offers.form', ['offer' => $offer]);
    }

    public function update(Request $request, Offer $offer)
    {
        $offer->update($this->validated($request, $offer));

        ActivityLogger::log('update', 'offer', 'Updated offer entry #' . $offer->id);

        return back()->with('success', 'Offer updated.');
    }

    public function destroy(Offer $offer)
    {
        ActivityLogger::log('delete', 'offer', 'Deleted offer entry #' . $offer->id);
        $offer->delete();

        return back()->with('success', 'Offer deleted.');
    }

    protected function validated(Request $request, ?Offer $model): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|string|max:255',
            'discount_text' => 'nullable|string|max:30',
            'badge' => 'nullable|string|max:50',
            'coupon_id' => 'nullable|integer|exists:coupons,id',
            'link_url' => 'nullable|string|max:255',
            'button_text' => 'nullable|string|max:50',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive',
        ]);

        return $validated;
    }
}
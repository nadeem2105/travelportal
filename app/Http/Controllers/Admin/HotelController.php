<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Hotel;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HotelController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $sortable = ['name', 'star_rating', 'starting_price', 'created_at'];
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $hotels = Hotel::query()
            ->with(['destination', 'rooms'])
            ->when($request->input('q'), fn ($query, $v) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$v}%")->orWhere('city', 'like', "%{$v}%")
            ))
            ->when($request->input('star_rating'), fn ($query, $v) => $query->where('star_rating', $v))
            ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
            ->when($request->input('city'), fn ($query, $v) => $query->where('city', $v))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->when(
                $request->input('sort'),
                fn ($query, $sort) => match ($sort) {
                    'price_low' => $query->orderBy('starting_price'),
                    'price_high' => $query->orderByDesc('starting_price'),
                    default => $query->latest(),
                },
                fn ($query) => $query->latest()
            ))
            ->paginate($perPage)
            ->withQueryString();

        $cities = Hotel::query()->whereNotNull('city')->where('city', '!=', '')
            ->distinct()->orderBy('city')->pluck('city');

        return view('admin.hotels.index', compact('hotels', 'cities'));
    }

    public function export(Request $request): StreamedResponse
    {
        $sortable = ['name', 'star_rating', 'starting_price', 'created_at'];
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'hotels-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'hotels', 'Exported hotels CSV');

        return response()->streamDownload(function () use ($request, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'City', 'Star Rating', 'Starting Price', 'Status']);

            Hotel::query()
                ->when($request->input('q'), fn ($query, $v) => $query->where(
                    fn ($q) => $q->where('name', 'like', "%{$v}%")->orWhere('city', 'like', "%{$v}%")
                ))
                ->when($request->input('star_rating'), fn ($query, $v) => $query->where('star_rating', $v))
                ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
                ->when($request->input('city'), fn ($query, $v) => $query->where('city', $v))
                ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
                ->when(! $applySort, fn ($query) => $query->when(
                    $request->input('sort'),
                    fn ($query, $sort) => match ($sort) {
                        'price_low' => $query->orderBy('starting_price'),
                        'price_high' => $query->orderByDesc('starting_price'),
                        default => $query->latest(),
                    },
                    fn ($query) => $query->latest()
                ))
                ->chunk(500, function ($hotels) use ($out) {
                    foreach ($hotels as $hotel) {
                        fputcsv($out, [
                            $hotel->id,
                            $hotel->name,
                            $hotel->city ?? '',
                            $hotel->star_rating,
                            $hotel->starting_price,
                            $hotel->status,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function create()
    {
        return view('admin.hotels.form', $this->formData(new Hotel()));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($request->name);

        $hotel = Hotel::create($data);

        ActivityLogger::log('create', 'hotels', "Created hotel {$hotel->name}");

        return redirect()->route('admin.hotels.edit', $hotel)->with('success', 'Hotel created. Add rooms next.');
    }

    public function edit(Hotel $hotel)
    {
        $hotel->load('rooms');

        return view('admin.hotels.form', $this->formData($hotel));
    }

    public function update(Request $request, Hotel $hotel)
    {
        $data = $this->validated($request);
        $data['slug'] = $hotel->slug;

        $hotel->update($data);

        ActivityLogger::log('update', 'hotels', "Updated hotel {$hotel->name}");

        return back()->with('success', 'Hotel updated.');
    }

    public function destroy(Hotel $hotel)
    {
        ActivityLogger::log('delete', 'hotels', "Deleted hotel {$hotel->name}");
        $hotel->delete();

        return redirect()->route('admin.hotels.index')->with('success', 'Hotel deleted.');
    }

    protected function formData(Hotel $hotel): array
    {
        return [
            'hotel' => $hotel,
            'destinations' => Destination::orderBy('name')->get(),
            'suppliers' => Supplier::forType('hotel')->get(),
        ];
    }

    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'destination_id' => 'nullable|integer|exists:destinations,id',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'supplier_code' => 'nullable|string|max:80',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:80',
            'star_rating' => 'required|integer|min:1|max:5',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|string|max:255',
            'photos' => 'nullable|string|max:2000',
            'amenities' => 'nullable|string|max:1000',
            'policies' => 'nullable|string|max:2000',
            'starting_price' => 'nullable|numeric|min:0',
            'is_featured' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
        ]);

        $validated['is_featured'] = $request->boolean('is_featured');

        foreach (['photos', 'amenities', 'policies'] as $field) {
            $validated[$field] = ! empty($validated[$field])
                ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $validated[$field]))))
                : null;
        }

        return $validated;
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (Hotel::where('slug', $slug)->exists()) {
            $slug = $base . '-' . ++$i;
        }

        return $slug;
    }
}

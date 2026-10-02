<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Hotel;
use App\Models\Package;
use App\Models\PackageDeparture;
use App\Models\PackageItinerary;
use App\Models\PackagePrice;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $sortable = ['name', 'base_price', 'created_at'];
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $packages = Package::query()
            ->with('destination')
            ->when($request->input('q'), fn ($query, $v) => $query->where('name', 'like', "%{$v}%"))
            ->when($request->input('destination_id'), fn ($query, $v) => $query->where('destination_id', $v))
            ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->when(
                $request->input('sort'),
                fn ($query, $sort) => match ($sort) {
                    'price_low' => $query->orderBy('base_price'),
                    'price_high' => $query->orderByDesc('base_price'),
                    default => $query->latest(),
                },
                fn ($query) => $query->latest()
            ))
            ->paginate($perPage)
            ->withQueryString();

        $destinations = Destination::orderBy('name')->get();

        return view('admin.packages.index', compact('packages', 'destinations'));
    }

    public function export(Request $request): StreamedResponse
    {
        $sortable = ['name', 'base_price', 'created_at'];
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'packages-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'packages', 'Exported packages CSV');

        return response()->streamDownload(function () use ($request, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'Destination', 'Base Price', 'Status', 'Created At']);

            Package::query()
                ->with('destination')
                ->when($request->input('q'), fn ($query, $v) => $query->where('name', 'like', "%{$v}%"))
                ->when($request->input('destination_id'), fn ($query, $v) => $query->where('destination_id', $v))
                ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
                ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
                ->when(! $applySort, fn ($query) => $query->when(
                    $request->input('sort'),
                    fn ($query, $sort) => match ($sort) {
                        'price_low' => $query->orderBy('base_price'),
                        'price_high' => $query->orderByDesc('base_price'),
                        default => $query->latest(),
                    },
                    fn ($query) => $query->latest()
                ))
                ->chunk(500, function ($packages) use ($out) {
                    foreach ($packages as $package) {
                        fputcsv($out, [
                            $package->id,
                            $package->name,
                            $package->destination?->name ?? '',
                            $package->base_price,
                            $package->status,
                            $package->created_at?->toDateTimeString(),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function create()
    {
        return view('admin.packages.form', [
            'package' => new Package(),
            'destinations' => Destination::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($request->name);

        $package = Package::create($data);

        ActivityLogger::log('create', 'packages', "Created package {$package->name}");

        return redirect()->route('admin.packages.edit', $package)->with('success', 'Package created. Add itinerary and pricing below.');
    }

    public function edit(Package $package)
    {
        $package->load([
            'itineraries', 'seasonalPrices', 'departures', 'hotels',
            'hotelSegments.options.hotel', 'hotelOptions.hotel', 'flightOptions',
        ]);

        return view('admin.packages.form', [
            'package' => $package,
            'destinations' => Destination::orderBy('name')->get(),
            'hotels' => Hotel::with(['rooms' => fn ($q) => $q->where('status', 'active')])
                ->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Package $package)
    {
        $data = $this->validated($request);
        $data['slug'] = $package->slug;

        $package->update($data);

        ActivityLogger::log('update', 'packages', "Updated package {$package->name}");

        return back()->with('success', 'Package updated.');
    }

    public function destroy(Package $package)
    {
        ActivityLogger::log('delete', 'packages', "Deleted package {$package->name}");
        $package->delete();

        return redirect()->route('admin.packages.index')->with('success', 'Package deleted.');
    }

    public function storeItinerary(Request $request, Package $package)
    {
        $validated = $request->validate([
            'day_number' => 'required|integer|min:1|max:30',
            'title' => 'required|string|max:150',
            'description' => 'required|string',
            'meals' => 'nullable|string|max:100',
            'overnight_stay' => 'nullable|string|max:150',
        ]);

        $package->itineraries()->create([
            'day_number' => $validated['day_number'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'meals' => $validated['meals'] ? array_map('trim', explode(',', $validated['meals'])) : null,
            'overnight_stay' => $validated['overnight_stay'] ?? null,
        ]);

        return back()->with('success', 'Itinerary day added.');
    }

    public function updateItinerary(Request $request, Package $package, PackageItinerary $itinerary)
    {
        abort_unless($itinerary->package_id === $package->id, 404);

        $validated = $request->validate([
            'day_number' => 'required|integer|min:1|max:30',
            'title' => 'required|string|max:150',
            'description' => 'required|string',
            'meals' => 'nullable|string|max:100',
            'overnight_stay' => 'nullable|string|max:150',
        ]);

        $itinerary->update([
            'day_number' => $validated['day_number'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'meals' => $validated['meals'] ? array_map('trim', explode(',', $validated['meals'])) : null,
            'overnight_stay' => $validated['overnight_stay'] ?? null,
        ]);

        ActivityLogger::log('update', 'packages', "Updated itinerary day {$validated['day_number']} of {$package->name}");

        return back()->with('success', 'Itinerary day updated.');
    }

    public function destroyItinerary(Request $request, Package $package, PackageItinerary $itinerary)
    {
        abort_unless($itinerary->package_id === $package->id, 404);

        $itinerary->delete();

        ActivityLogger::log('delete', 'packages', "Deleted itinerary day {$itinerary->day_number} of {$package->name}");

        return back()->with('success', 'Itinerary day removed.');
    }

    public function storePrice(Request $request, Package $package)
    {
        $validated = $request->validate([
            'season' => 'required|string|max:50',
            'label' => 'nullable|string|max:80',
            'price_per_person' => 'required|numeric|min:0',
            'price_per_couple' => 'nullable|numeric|min:0',
            'price_per_child' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $package->seasonalPrices()->create($validated);

        return back()->with('success', 'Seasonal price added.');
    }

    public function storeDeparture(Request $request, Package $package)
    {
        $validated = $request->validate([
            'departure_date' => 'required|date|after:today',
            'inventory' => 'required|integer|min:1|max:100',
            'price_override' => 'nullable|numeric|min:0',
        ]);

        $package->departures()->create($validated + ['status' => 'open']);

        return back()->with('success', 'Departure date added.');
    }

    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'destination_id' => 'nullable|integer|exists:destinations,id',
            'duration_days' => 'required|integer|min:1|max:60',
            'duration_nights' => 'required|integer|min:0|max:59',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|string|max:255',
            'gallery' => 'nullable|string|max:2000',
            'highlights' => 'nullable|string|max:1000',
            'inclusions' => 'nullable|string|max:1000',
            'exclusions' => 'nullable|string|max:1000',
            'cancellation_policy' => 'nullable|string|max:2000',
            'base_price' => 'required|numeric|min:0',
            'child_price' => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:90',
            'package_type' => 'required|in:group,private,custom',
            'hotel_mode' => 'nullable|in:none,optional,required',
            'flight_mode' => 'nullable|in:none,optional,required',
            'max_travellers' => 'required|integer|min:1|max:100',
            'status' => 'required|in:active,inactive,draft',
            'is_featured' => 'nullable|boolean',
        ]);

        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['discount_percent'] = $validated['discount_percent'] ?? 0;
        $validated['hotel_mode'] = $validated['hotel_mode'] ?? 'none';
        $validated['flight_mode'] = $validated['flight_mode'] ?? 'none';

        foreach (['gallery', 'highlights', 'inclusions', 'exclusions'] as $field) {
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

        while (Package::where('slug', $slug)->exists()) {
            $slug = $base . '-' . ++$i;
        }

        return $slug;
    }
}

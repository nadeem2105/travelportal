<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DestinationController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $destinations = Destination::withCount('packages')
            ->when($request->input('q'), fn ($query, $v) => $query->where('name', 'like', "%{$v}%"))
            ->when($request->input('status'), fn ($query, $v) => $query->where('status', $v))
            ->orderBy('sort_order')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.destinations.index', compact('destinations'));
    }

    public function create()
    {
        return view('admin.destinations.form', ['destination' => new Destination()]);
    }

    public function store(Request $request)
    {
        Destination::create($this->validated($request) + ['slug' => $this->uniqueSlug($request->name)]);

        ActivityLogger::log('create', 'destinations', "Created destination {$request->name}");

        return redirect()->route('admin.destinations.index')->with('success', 'Destination created.');
    }

    public function edit(Destination $destination)
    {
        return view('admin.destinations.form', ['destination' => $destination]);
    }

    public function update(Request $request, Destination $destination)
    {
        $destination->update($this->validated($request));

        ActivityLogger::log('update', 'destinations', "Updated destination {$destination->name}");

        return back()->with('success', 'Destination updated.');
    }

    public function destroy(Destination $destination)
    {
        ActivityLogger::log('delete', 'destinations', "Deleted destination {$destination->name}");
        $destination->delete();

        return back()->with('success', 'Destination deleted.');
    }

    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'region' => 'nullable|string|max:100',
            'cover_image' => 'nullable|string|max:255',
            'gallery' => 'nullable|string|max:2000',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'best_time' => 'nullable|string|max:100',
            'altitude' => 'nullable|string|max:50',
            'famous_for' => 'nullable|string|max:150',
            'places_to_visit' => 'nullable|string|max:1500',
            'things_to_do' => 'nullable|string|max:1500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'sort_order' => 'nullable|integer|min:0',
            'is_featured' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
        ]);

        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        foreach (['gallery', 'places_to_visit', 'things_to_do'] as $field) {
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

        while (Destination::where('slug', $slug)->exists()) {
            $slug = $base . '-' . ++$i;
        }

        return $slug;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoMetadata;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class SeoController extends Controller
{
    public function index()
    {
        $pageKeys = [
            'home' => 'Homepage',
            'flights' => 'Flights',
            'hotels' => 'Hotels',
            'cabs' => 'Cabs',
            'packages' => 'Packages',
            'destinations' => 'Destinations',
            'guides' => 'Kashmir Guide',
            'blog' => 'Blog',
            'offers' => 'Offers',
            'contact' => 'Contact',
            'faq' => 'FAQ',
            'reviews' => 'Reviews',
        ];

        $seoEntries = SeoMetadata::whereNull('entity_id')->whereIn('page_key', array_keys($pageKeys))
            ->get()
            ->keyBy('page_key');

        return view('admin.seo.index', compact('pageKeys', 'seoEntries'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'page_key' => 'required|string|max:50',
            'seo_title' => 'nullable|string|max:150',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:255',
            'canonical_url' => 'nullable|string|max:255',
            'og_title' => 'nullable|string|max:150',
            'og_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|string|max:255',
            'schema_json' => 'nullable|string',
            'robots' => 'nullable|string|max:50',
        ]);

        $schema = null;
        if (! empty($validated['schema_json'])) {
            $schema = json_decode($validated['schema_json']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->with('error', 'Schema must be valid JSON-LD.');
            }
            $schema = json_encode($schema, JSON_UNESCAPED_SLASHES);
        }

        SeoMetadata::updateOrCreate(
            ['page_key' => $validated['page_key'], 'entity_type' => 'page', 'entity_id' => null],
            array_merge(collect($validated)->except('page_key')->all(), ['schema_json' => $schema])
        );

        ActivityLogger::log('update', 'seo', "Updated SEO for {$validated['page_key']}");

        return back()->with('success', 'SEO settings saved.');
    }
}

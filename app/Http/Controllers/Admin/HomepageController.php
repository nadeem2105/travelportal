<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageSection;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

/**
 * Homepage Builder: every homepage section is editable, reorderable and
 * toggleable — no code changes needed for routine content updates.
 */
class HomepageController extends Controller
{
    public function index()
    {
        $sections = HomepageSection::orderBy('sort_order')->get();

        return view('admin.homepage.index', compact('sections'));
    }

    public function update(Request $request, HomepageSection $section)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:150',
            'subtitle' => 'nullable|string|max:255',
            'cta_text' => 'nullable|string|max:50',
            'cta_url' => 'nullable|string|max:255',
            'image' => 'nullable|string|max:255',
            'background' => 'nullable|string|max:255',
            'content' => 'nullable|array',
        ]);

        $section->update($validated);

        ActivityLogger::log('update', 'homepage', "Updated homepage section {$section->key}");

        return back()->with('success', "Section '{$section->name}' updated.");
    }

    public function toggle(HomepageSection $section)
    {
        $section->update(['is_enabled' => ! $section->is_enabled]);

        ActivityLogger::log('update', 'homepage', ($section->is_enabled ? 'Enabled' : 'Disabled') . " section {$section->key}");

        return back()->with('success', "Section '{$section->name}' " . ($section->is_enabled ? 'enabled' : 'disabled') . '.');
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:homepage_sections,id',
        ]);

        foreach ($validated['order'] as $index => $id) {
            HomepageSection::where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['ok' => true]);
    }
}

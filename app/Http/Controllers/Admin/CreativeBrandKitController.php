<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreativeBrandKit;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

/**
 * Brand Kit CRUD for the Ad Creative Studio. Every generated creative follows
 * the selected (or default) kit. Seeds a default from the portal settings() on
 * first visit so it is never empty.
 */
class CreativeBrandKitController extends Controller
{
    public function index()
    {
        CreativeBrandKit::active(); // ensure a default exists

        return view('admin.marketing.studio.brand_kits', [
            'pageTitle' => 'Brand Kits',
            'kits' => CreativeBrandKit::orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = auth('admin')->id();
        $kit = CreativeBrandKit::create($data);
        $this->syncDefault($kit, $request->boolean('is_default'));
        ActivityLogger::log('create', 'creative_studio', 'Brand kit created: ' . $kit->name, ['id' => $kit->id]);

        return back()->with('success', 'Brand kit created.');
    }

    public function update(Request $request, CreativeBrandKit $brandKit)
    {
        $brandKit->update($this->validated($request));
        $this->syncDefault($brandKit, $request->boolean('is_default'));
        ActivityLogger::log('update', 'creative_studio', 'Brand kit updated: ' . $brandKit->name, ['id' => $brandKit->id]);

        return back()->with('success', 'Brand kit updated.');
    }

    public function destroy(CreativeBrandKit $brandKit)
    {
        if ($brandKit->is_default) {
            return back()->with('error', 'Cannot delete the default brand kit. Set another as default first.');
        }
        $brandKit->delete();

        return back()->with('success', 'Brand kit deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'is_default' => 'nullable|boolean',
            'brand_name' => 'nullable|string|max:120',
            'logo_path' => 'nullable|string|max:255',
            'primary_color' => 'nullable|string|max:9',
            'secondary_color' => 'nullable|string|max:9',
            'accent_color' => 'nullable|string|max:9',
            'text_color' => 'nullable|string|max:9',
            'font_family' => 'nullable|string|max:80',
            'website' => 'nullable|string|max:190',
            'phone' => 'nullable|string|max:40',
            'whatsapp' => 'nullable|string|max:40',
            'email' => 'nullable|string|max:190',
            'address' => 'nullable|string|max:190',
            'default_cta' => 'nullable|string|max:40',
            'default_disclaimer' => 'nullable|string|max:190',
            'status' => 'nullable|in:active,inactive',
        ]);
    }

    private function syncDefault(CreativeBrandKit $kit, bool $isDefault): void
    {
        if ($isDefault) {
            CreativeBrandKit::where('id', '!=', $kit->id)->update(['is_default' => false]);
            $kit->update(['is_default' => true]);
        }
    }
}

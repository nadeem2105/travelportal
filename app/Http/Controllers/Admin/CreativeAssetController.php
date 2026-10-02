<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingAsset;
use App\Models\MarketingCreative;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Creative media library. Searchable, filterable list of portal-sourced,
 * uploaded and AI-generated assets. Reuses the public 'public' disk. Warns
 * before deleting an asset used by a creative.
 */
class CreativeAssetController extends Controller
{
    public function index(Request $request)
    {
        $q = MarketingAsset::query()->latest();

        if ($request->filled('category')) {
            $q->where('category', $request->string('category'));
        }
        if ($request->filled('source')) {
            $q->where('source', $request->string('source'));
        }
        if ($request->filled('search')) {
            $q->where('name', 'like', '%' . $request->string('search') . '%');
        }

        return view('admin.marketing.studio.assets', [
            'pageTitle' => 'Media Library',
            'assets' => $q->paginate(30)->withQueryString(),
            'filters' => $request->only(['category', 'source', 'search']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp|max:8192',
            'name' => 'nullable|string|max:150',
            'category' => 'nullable|string|max:30',
        ]);

        $file = $request->file('file');
        $path = $file->store('marketing/assets', 'public');
        [$w, $h] = @getimagesize($file->getRealPath()) ?: [null, null];

        $asset = MarketingAsset::create([
            'name' => $request->input('name') ?: $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $w,
            'height' => $h,
            'dimensions' => $w && $h ? "{$w}x{$h}" : null,
            'category' => $request->input('category', 'image'),
            'source' => 'upload',
            'license_status' => 'owned',
            'status' => 'ready',
            'created_by' => auth('admin')->id(),
        ]);
        ActivityLogger::log('create', 'creative_studio', 'Asset uploaded: ' . $asset->name, ['id' => $asset->id]);

        return back()->with('success', 'Asset uploaded.');
    }

    public function destroy(MarketingAsset $asset)
    {
        $inUse = MarketingCreative::where('asset_id', $asset->id)->exists();
        if ($inUse && ! request()->boolean('force')) {
            return back()->with('error', 'This asset is used by one or more creatives. Confirm deletion to proceed.');
        }

        try {
            if ($asset->path) {
                Storage::disk('public')->delete($asset->path);
            }
        } catch (\Throwable $e) {
        }
        $asset->delete();

        return back()->with('success', 'Asset deleted.');
    }
}

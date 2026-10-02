<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function index(Request $request)
    {
        $media = Media::query()
            ->when(trim((string) $request->query('q')), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->when($request->query('folder'), fn ($q, $folder) => $q->where('folder', $folder))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return view('admin.media.index', compact('media'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,svg,gif|max:4096',
            'folder' => 'nullable|string|max:100',
        ]);

        $file = $request->file('file');
        $path = $file->store($request->input('folder', 'media'), 'public');

        $dimension = null;
        if (in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $info = @getimagesize($file->getRealPath());
            $dimension = $info ? "{$info[0]}x{$info[1]}" : null;
        }

        $media = Media::create([
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_name' => $file->hashName(),
            'disk' => 'public',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'dimension' => $dimension,
            'folder' => $request->input('folder', 'media'),
            'uploaded_by' => auth('admin')->id(),
        ]);

        ActivityLogger::log('create', 'media', "Uploaded {$media->name}");

        return back()->with('success', 'File uploaded.');
    }

    /**
     * AJAX upload used by the inline image-upload components across admin forms.
     * Returns the stored path (set as the field value) + public URL for preview.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,svg,gif|max:4096',
            'folder' => 'nullable|string|max:100',
        ]);

        $file = $request->file('file');
        $folder = $request->input('folder', 'media');
        $path = $file->store($folder, 'public');

        $dimension = null;
        if (in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $info = @getimagesize($file->getRealPath());
            $dimension = $info ? "{$info[0]}x{$info[1]}" : null;
        }

        $media = Media::create([
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_name' => $file->hashName(),
            'disk' => 'public',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'dimension' => $dimension,
            'folder' => $folder,
            'uploaded_by' => auth('admin')->id(),
        ]);

        return response()->json([
            'path' => $media->path,
            'url' => $media->url,
            'name' => $media->name,
        ]);
    }

    public function destroy(Media $media)
    {
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();

        ActivityLogger::log('delete', 'media', "Deleted media {$media->name}");

        return back()->with('success', 'File deleted.');
    }
}

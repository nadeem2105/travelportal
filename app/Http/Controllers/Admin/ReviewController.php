<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->input('per_page'), [15, 25, 50, 100]) ? (int) $request->input('per_page') : 15;

        $sortable = ['rating', 'created_at'];
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $reviews = Review::with(['user', 'reviewable'])
            ->when($request->input('q'), fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$v}%")
                ->orWhere('content', 'like', "%{$v}%")))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('rating'), fn ($q, $r) => $q->where('rating', (int) $r))
            ->when($applySort, fn ($q) => $q->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($q) => $q->latest())
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.reviews.index', compact('reviews'));
    }

    public function export(Request $request): StreamedResponse
    {
        $sortable = ['rating', 'created_at'];
        $sortCol = $request->input('sort_col');
        $sortDir = $request->input('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'reviews-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'reviews', 'Exported reviews CSV');

        return response()->streamDownload(function () use ($request, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Type', 'Rating', 'Title', 'Status', 'Created At', 'User']);

            Review::with('user')
                ->when($request->input('q'), fn ($q, $v) => $q->where(fn ($w) => $w
                    ->where('title', 'like', "%{$v}%")
                    ->orWhere('content', 'like', "%{$v}%")))
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->when($request->input('rating'), fn ($q, $r) => $q->where('rating', (int) $r))
                ->when($applySort, fn ($q) => $q->orderBy($sortCol, $sortDir))
                ->when(! $applySort, fn ($q) => $q->latest())
                ->chunk(500, function ($reviews) use ($out) {
                    foreach ($reviews as $review) {
                        fputcsv($out, [
                            $review->id,
                            class_basename($review->reviewable_type),
                            $review->rating,
                            $review->title,
                            $review->status,
                            $review->created_at?->toDateTimeString(),
                            $review->user?->name ?? '',
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function updateStatus(Request $request, Review $review)
    {
        $validated = $request->validate(['status' => 'required|in:pending,approved,rejected']);

        $review->update(['status' => $validated['status']]);

        ActivityLogger::log('update', 'reviews', "Review #{$review->id} → {$review->status}");

        return back()->with('success', 'Review status updated.');
    }

    public function feature(Review $review)
    {
        $review->update(['is_featured' => ! $review->is_featured]);

        return back()->with('success', 'Review ' . ($review->is_featured ? 'featured' : 'unfeatured') . '.');
    }
}

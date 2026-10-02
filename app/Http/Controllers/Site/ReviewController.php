<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $reviews = Review::approved()
            ->with('reviewable')
            ->when($request->query('type'), fn ($q, $type) => $q->where('reviewable_type', match ($type) {
                'package' => \App\Models\Package::class,
                'hotel' => \App\Models\Hotel::class,
                'cab' => \App\Models\Vehicle::class,
                default => $type,
            }))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('reviews.index', [
            'seo' => app(\App\Services\SeoService::class)->forPage('reviews', null, ['title' => 'Traveller Reviews']),
            'reviews' => $reviews,
        ]);
    }
}

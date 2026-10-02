<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    public function index()
    {
        return view('destinations.index', [
            'seo' => app(\App\Services\SeoService::class)->forPage('destinations'),
            'destinations' => Destination::where('status', 'active')->orderBy('sort_order')->paginate(12),
        ]);
    }

    public function show(Request $request, Destination $destination)
    {
        if ($destination->status !== 'active') {
            abort(404);
        }

        \App\Services\AnalyticsService::track('view_destination', [
            'product_type' => 'destination',
            'product_id' => $destination->id,
            'destination_name' => $destination->name,
        ]);

        return view('destinations.show', [
            'seo' => app(\App\Services\SeoService::class)->forPage('destinations', $destination, [
                'title' => $destination->name . ' Travel Guide',
                'description' => $destination->short_description,
            ]),
            'destination' => $destination,
            'packages' => $destination->packages()->with('seasonalPrices')->where('status', 'active')->limit(6)->get(),
            'hotels' => $destination->hotels()->where('status', 'active')->limit(6)->get(),
        ]);
    }
}

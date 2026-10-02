<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function index()
    {
        return view('offers.index', [
            'seo' => app(\App\Services\SeoService::class)->forPage('offers'),
            'offers' => Offer::active()->paginate(9),
        ]);
    }

    public function show(Request $request, Offer $offer)
    {
        if ($offer->status !== 'active') {
            abort(404);
        }

        return view('offers.show', [
            'seo' => ['title' => $offer->title, 'description' => $offer->description],
            'offer' => $offer,
        ]);
    }
}

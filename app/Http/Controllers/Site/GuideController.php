<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Guide;
use Illuminate\Http\Request;

class GuideController extends Controller
{
    public function index(Request $request)
    {
        $query = Guide::where('status', 'active')->orderBy('sort_order');

        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('excerpt', 'like', "%{$q}%"));
        }

        return view('guides.index', [
            'seo' => app(\App\Services\SeoService::class)->forPage('guides'),
            'guides' => $query->paginate(9)->withQueryString(),
        ]);
    }

    public function show(Request $request, Guide $guide)
    {
        if ($guide->status !== 'active') {
            abort(404);
        }

        return view('guides.show', [
            'seo' => app(\App\Services\SeoService::class)->forPage('guides', $guide, [
                'title' => $guide->title,
                'description' => $guide->excerpt,
            ]),
            'guide' => $guide,
            'related' => Guide::where('status', 'active')->where('id', '!=', $guide->id)->limit(3)->get(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\Destination;
use App\Models\Faq;
use App\Models\Guide;
use App\Models\HomepageSection;
use App\Models\Hotel;
use App\Models\Offer;
use App\Models\Package;
use App\Models\Testimonial;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $sections = HomepageSection::enabled()->get();

        $needsDestinations = $sections->contains('type', 'destinations');
        $needsPackages = $sections->contains('type', 'packages');
        $needsTestimonials = $sections->contains('type', 'testimonials');
        $needsHotels = $sections->contains('type', 'hotels');
        $needsGuide = $sections->contains('type', 'guide');
        $needsFaq = $sections->contains('type', 'faq');

        return view('home.index', [
            'seo' => app(\App\Services\SeoService::class)->forPage('home'),
            'sections' => $sections,
            'widgetTab' => $request->session()->get('widget_tab', 'flights'),
            'destinations' => $needsDestinations
                ? Destination::where('status', 'active')->orderBy('sort_order')->limit(6)->get()
                : collect(),
            'packages' => $needsPackages
                ? Package::with('destination')->where('status', 'active')->orderByDesc('is_featured')->limit(4)->get()
                : collect(),
            'hotels' => $needsHotels
                ? Hotel::with('destination')->where('status', 'active')->orderByDesc('is_featured')->latest()->limit(6)->get()
                : collect(),
            'testimonials' => $needsTestimonials
                ? Testimonial::where('status', 'active')->orderByDesc('is_featured')->latest()->limit(6)->get()
                : collect(),
            'posts' => $needsGuide
                ? Guide::where('status', 'active')->orderBy('sort_order')->latest()->limit(3)->get()
                    ->map(fn ($g) => (object) $g->toSearchResult('guide'))
                : collect(),
            'faqs' => $needsFaq
                ? Faq::where('status', 'active')->orderBy('sort_order')->limit(6)->get()
                : collect(),
            'offers' => Offer::active()->with('coupon')->limit(8)->get(),
            'features' => $this->whyChooseFeatures($sections),
        ]);
    }

    protected function whyChooseFeatures($sections): array
    {
        $section = $sections->firstWhere('type', 'why_choose');

        $defaults = [
            ['icon' => $this->icon('map-pin'), 'title' => 'Local Expertise', 'text' => 'Deep knowledge of Kashmir'],
            ['icon' => $this->icon('tag'), 'title' => 'Best Price Guarantee', 'text' => 'Unbeatable deals always'],
            ['icon' => $this->icon('sliders'), 'title' => 'Customized Trips', 'text' => 'Travel your way'],
            ['icon' => $this->icon('headset'), 'title' => '24/7 Support', 'text' => 'We are always here for you'],
        ];

        if (! $section || empty($section->content['features'])) {
            return $defaults;
        }

        return array_map(function ($feature, $i) {
            return [
                'icon' => $this->icon($feature['icon'] ?? 'star'),
                'title' => $feature['title'],
                'text' => $feature['text'],
            ];
        }, $section->content['features'], array_keys($section->content['features']));
    }

    protected function icon(string $name): string
    {
        return match ($name) {
            'map-pin' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>',
            'tag' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.567 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>',
            'sliders' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"/></svg>',
            'headset' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12v3.75A2.25 2.25 0 0 0 6 18h.75a1.5 1.5 0 0 0 1.5-1.5v-3A1.5 1.5 0 0 0 6.75 12H3.75Zm0 0a8.25 8.25 0 1 1 16.5 0m0 0v3.75m0 0A2.25 2.25 0 0 1 18 18h-.75a1.5 1.5 0 0 1-1.5-1.5v-3A1.5 1.5 0 0 1 17.25 12h3.75Z"/></svg>',
            default => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m12 2 2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2l-6.1 3.4 1.4-6.8L2.2 9.1l6.9-.8L12 2Z"/></svg>',
        };
    }

    public function globalSearch(Request $request)
    {
        $q = trim((string) $request->query('q'));

        if ($q === '') {
            return redirect()->route('home');
        }

        $packages = Package::where('status', 'active')->where(fn ($query) => $query
            ->where('name', 'like', "%{$q}%")
            ->orWhere('short_description', 'like', "%{$q}%"))->limit(6)->get();

        $destinations = Destination::where('status', 'active')->where(fn ($query) => $query
            ->where('name', 'like', "%{$q}%")
            ->orWhere('short_description', 'like', "%{$q}%"))->limit(6)->get();

        $hotels = \App\Models\Hotel::where('status', 'active')->where('name', 'like', "%{$q}%")->limit(6)->get();

        $articles = collect()
            ->merge(Guide::where('status', 'active')->where('title', 'like', "%{$q}%")->limit(4)->get()->map->toSearchResult('Guide'))
            ->merge(Blog::published()->where('title', 'like', "%{$q}%")->limit(4)->get()->map->toSearchResult('Blog'));

        return view('search', compact('q', 'packages', 'destinations', 'hotels', 'articles'));
    }
}

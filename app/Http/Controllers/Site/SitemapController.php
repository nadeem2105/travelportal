<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\Destination;
use App\Models\Guide;
use App\Models\Hotel;
use App\Models\Package;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = [];

        // Static routes
        $staticRoutes = [
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => route('packages.index'), 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => route('hotels.index'), 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => route('flights.index'), 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => route('cabs.index'), 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => route('destinations.index'), 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => route('blog.index'), 'priority' => '0.8', 'changefreq' => 'daily'],
            ['loc' => route('guide.index'), 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => route('contact'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => route('faq'), 'priority' => '0.5', 'changefreq' => 'monthly'],
        ];

        foreach ($staticRoutes as $r) {
            $urls[] = $r + ['lastmod' => now()->startOfDay()->toAtomString()];
        }

        // Active Destinations
        foreach (Destination::where('status', 'active')->get() as $dest) {
            $urls[] = [
                'loc' => route('destinations.show', $dest->slug),
                'priority' => '0.8',
                'changefreq' => 'weekly',
                'lastmod' => optional($dest->updated_at)->toAtomString() ?? now()->toAtomString(),
            ];
        }

        // Active Packages
        foreach (Package::where('status', 'active')->get() as $pkg) {
            $urls[] = [
                'loc' => route('packages.show', $pkg->slug),
                'priority' => '0.9',
                'changefreq' => 'daily',
                'lastmod' => optional($pkg->updated_at)->toAtomString() ?? now()->toAtomString(),
            ];
        }

        // Active Hotels
        foreach (Hotel::where('status', 'active')->get() as $hotel) {
            $urls[] = [
                'loc' => route('hotels.show', $hotel->slug),
                'priority' => '0.8',
                'changefreq' => 'weekly',
                'lastmod' => optional($hotel->updated_at)->toAtomString() ?? now()->toAtomString(),
            ];
        }

        // Published Blogs
        foreach (Blog::where('status', 'published')->get() as $blog) {
            $urls[] = [
                'loc' => route('blog.show', $blog->slug),
                'priority' => '0.7',
                'changefreq' => 'monthly',
                'lastmod' => optional($blog->updated_at)->toAtomString() ?? now()->toAtomString(),
            ];
        }

        // Published Guides
        foreach (Guide::where('status', 'published')->get() as $guide) {
            $urls[] = [
                'loc' => route('guide.show', $guide->slug),
                'priority' => '0.7',
                'changefreq' => 'monthly',
                'lastmod' => optional($guide->updated_at)->toAtomString() ?? now()->toAtomString(),
            ];
        }

        $xml = view('sitemap.xml', compact('urls'))->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $content = "User-agent: *\n"
            . "Allow: /\n"
            . "Disallow: /admin/\n"
            . "Disallow: /account/\n"
            . "Disallow: /checkout/\n"
            . "Disallow: /booking/*/confirmation\n"
            . "Disallow: /api/\n\n"
            . "Sitemap: " . url('/sitemap.xml') . "\n";

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}

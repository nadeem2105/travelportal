<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $query = Blog::published()->latest('published_at');

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('excerpt', 'like', "%{$q}%"));
        }

        return view('blogs.index', [
            'seo' => app(\App\Services\SeoService::class)->forPage('blog'),
            'blogs' => $query->paginate(9)->withQueryString(),
            'categories' => Blog::published()->distinct()->pluck('category'),
        ]);
    }

    public function show(Request $request, Blog $blog)
    {
        abort_unless($blog->status === 'published' && optional($blog->published_at)->isPast(), 404);

        $blog->increment('views');

        return view('blogs.show', [
            'seo' => app(\App\Services\SeoService::class)->forPage('blog', $blog, [
                'title' => $blog->title,
                'description' => $blog->excerpt,
            ]),
            'blog' => $blog,
            'recent' => Blog::published()->where('id', '!=', $blog->id)->latest('published_at')->limit(3)->get(),
        ]);
    }
}

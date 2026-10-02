@extends('layouts.site')
@section('activeNav', 'blog')

@section('page')
<section class="shell pt-28">
    <h1 class="font-display text-3xl font-bold">Travel Blog</h1>
    <p class="section-sub mt-1">Stories, tips and hidden gems from the valley</p>

    <div class="mt-6 flex flex-wrap gap-2">
        <a href="{{ route('blog.index') }}" class="rounded-full px-4 py-1.5 text-xs font-semibold {{ ! request('category') ? 'bg-brand-600 text-white' : 'bg-white text-ink-700 ring-1 ring-slate-200' }}">All</a>
        @foreach ($categories as $category)
            <a href="{{ route('blog.index', ['category' => $category]) }}" class="rounded-full px-4 py-1.5 text-xs font-semibold {{ request('category') === $category ? 'bg-brand-600 text-white' : 'bg-white text-ink-700 ring-1 ring-slate-200' }}">{{ $category }}</a>
        @endforeach
    </div>

    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($blogs as $blog)
            <a href="{{ route('blog.show', $blog) }}" class="card card-hover group overflow-hidden">
                <span class="block h-44 overflow-hidden">
                    <img src="{{ asset(img($blog->cover_image, 'images/blogs/valley-guide.svg')) }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" alt="{{ $blog->title }}" loading="lazy">
                </span>
                <span class="block p-5">
                    <span class="badge-soft">{{ $blog->category }}</span>
                    <span class="font-display mt-2 block text-base font-bold text-ink-900 group-hover:text-brand-700">{{ $blog->title }}</span>
                    <span class="mt-1 block text-sm text-ink-500">{{ \Illuminate\Support\Str::limit($blog->excerpt, 100) }}</span>
                    <span class="mt-3 block text-xs text-ink-300">{{ optional($blog->published_at)->format('d M Y') }} · {{ $blog->views }} views</span>
                </span>
            </a>
        @empty
            <p class="text-ink-500">No blog posts yet.</p>
        @endforelse
    </div>

    <div class="mt-8">{{ $blogs->links() }}</div>
</section>
@endsection

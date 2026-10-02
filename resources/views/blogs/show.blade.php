@extends('layouts.site')

@section('page')
<section class="shell max-w-3xl pt-28">
    <nav class="text-xs text-ink-500">
        <a href="{{ route('home') }}" class="hover:text-brand-700">Home</a> ›
        <a href="{{ route('blog.index') }}" class="hover:text-brand-700">Blog</a>
    </nav>

    <h1 class="font-display mt-3 text-3xl font-bold">{{ $blog->title }}</h1>
    <p class="mt-2 text-xs text-ink-500">{{ optional($blog->published_at)->format('d M Y') }} · {{ $blog->views }} views · {{ $blog->category }}</p>
    @if ($blog->cover_image)
        <img src="{{ asset(img($blog->cover_image, 'images/blogs/valley-guide.svg')) }}" class="mt-5 h-72 w-full rounded-2xl object-cover" alt="{{ $blog->title }}">
    @endif

    <div class="prose-page mt-6">
        {!! nl2br(e($blog->content)) !!}
    </div>

    @if ($blog->tags)
        <div class="mt-6 flex flex-wrap gap-2">
            @foreach ($blog->tags as $tag)
                <span class="badge-soft">#{{ $tag }}</span>
            @endforeach
        </div>
    @endif

    @if ($recent->count())
        <h2 class="font-display mt-10 text-xl font-bold">Recent Posts</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            @foreach ($recent as $post)
                <a href="{{ route('blog.show', $post) }}" class="card card-hover p-4 text-sm font-semibold text-ink-900 hover:text-brand-700">{{ \Illuminate\Support\Str::limit($post->title, 60) }}</a>
            @endforeach
        </div>
    @endif
</section>
@endsection

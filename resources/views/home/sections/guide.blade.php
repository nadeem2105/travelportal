{{-- Kashmir Guide / blog teasers --}}
@php($posts = $posts ?? collect())
@if ($posts->count())
    <section class="shell py-10">
        @include('home.sections._header', [
            'title' => $section->title ?? 'Kashmir Travel Guide',
            'subtitle' => $section->subtitle ?? 'Stories, tips and hidden gems',
            'ctaText' => $section->cta_text ?? 'Read The Guide',
            'ctaUrl' => route('guide.index'),
        ])
        <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($posts as $post)
                <a href="{{ $post->type === 'guide' ? route('guide.show', $post->slug) : route('blog.show', $post->slug) }}" class="card card-hover group overflow-hidden">
                    <span class="block h-40 overflow-hidden">
                        <img src="{{ asset(img($post->cover_image, 'images/guides/' . $post->slug . '.svg')) }}" alt="{{ $post->title }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                    </span>
                    <span class="block p-4">
                        <span class="badge-soft">{{ ucfirst($post->type) }}</span>
                        <span class="font-display mt-2 block text-sm font-bold text-ink-900 group-hover:text-brand-700">{{ Str::limit($post->title, 60) }}</span>
                        <span class="mt-1 block text-xs text-ink-500">{{ Str::limit($post->excerpt, 90) }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endif

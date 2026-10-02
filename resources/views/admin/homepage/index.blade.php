@extends('layouts.admin')
@section('pageTitle', 'Homepage Builder')

@section('content')
    <h1 class="font-display text-xl font-bold">Homepage Builder</h1>
    <p class="mt-1 text-sm text-ink-500">Toggle sections, edit titles/subtitles/CTAs, and reorder. Changes appear on the live site instantly.</p>

    <div class="mt-4 space-y-3" id="section-list">
        @foreach ($sections as $section)
            <details class="admin-card" data-id="{{ $section->id }}">
                <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2">
                    <span class="font-display font-bold">{{ $section->name }}
                        <span class="ml-1 text-xs font-medium text-ink-500">type: {{ $section->type }}</span>
                    </span>
                    <span class="flex items-center gap-2">
                        <span class="status-pill {{ $section->is_enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                            {{ $section->is_enabled ? 'Visible' : 'Hidden' }}
                        </span>
                        <form action="{{ route('admin.homepage.toggle', $section) }}" method="POST">
                            @csrf
                            <button class="btn-ghost btn-sm">{{ $section->is_enabled ? 'Disable' : 'Enable' }}</button>
                        </form>
                    </span>
                </summary>

                <form action="{{ route('admin.homepage.update', $section) }}" method="POST" class="mt-4">
                    @csrf @method('PUT')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><label class="label">Title</label><input type="text" name="title" class="input" value="{{ $section->title }}"></div>
                        <div><label class="label">Subtitle</label><input type="text" name="subtitle" class="input" value="{{ $section->subtitle }}"></div>
                        <div><label class="label">CTA Text</label><input type="text" name="cta_text" class="input" value="{{ $section->cta_text }}"></div>
                        <div><label class="label">CTA URL</label><input type="text" name="cta_url" class="input" value="{{ $section->cta_url }}"></div>
                        <div><label class="label">Image</label><x-admin.image-upload name="image" :value="$section->image" folder="homepage" /></div>
                        <div><label class="label">Background</label><input type="text" name="background" class="input" value="{{ $section->background }}"></div>
                    </div>
                    @if ($section->type === 'why_choose')
                        <p class="mt-3 text-xs text-ink-500">Feature items for this section are seeded defaults; you can override the heading/subtitle above.</p>
                    @endif
                    <button class="btn-primary btn-sm mt-4">Save Section</button>
                </form>
            </details>
        @endforeach
    </div>
@endsection

@extends('layouts.admin')
@section('pageTitle', 'SEO Manager')

@section('content')
    <h1 class="font-display text-xl font-bold">SEO Manager</h1>
    <p class="mt-1 text-sm text-ink-500">Per-page meta tags, Open Graph and JSON-LD schema. Entity-level SEO (packages, hotels, destinations) can also be attached in each module.</p>

    <form method="GET" class="mt-3 flex gap-2">
        <select name="page" class="input !w-64" onchange="this.form.submit()">
            @foreach ($pageKeys as $key => $label)
                <option value="{{ $key }}" @selected(request('page', 'home') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </form>

    @php($currentKey = request('page', 'home'))
    @php($entry = $seoEntries->get($currentKey))

    <form action="{{ route('admin.seo.update') }}" method="POST" class="admin-card mt-4 max-w-3xl">
        @csrf @method('PUT')
        <input type="hidden" name="page_key" value="{{ $currentKey }}">

        <div class="grid gap-4">
            <div><label class="label">SEO Title</label><input type="text" name="seo_title" class="input" value="{{ $entry->seo_title ?? '' }}" placeholder="{{ settings('company_name') }} — Book Kashmir Flights, Hotels & Packages"></div>
            <div><label class="label">Meta Description</label><textarea name="meta_description" rows="2" class="input">{{ $entry->meta_description ?? '' }}</textarea></div>
            <div><label class="label">Meta Keywords</label><input type="text" name="meta_keywords" class="input" value="{{ $entry->meta_keywords ?? '' }}"></div>
            <div><label class="label">Canonical URL</label><input type="text" name="canonical_url" class="input" value="{{ $entry->canonical_url ?? '' }}"></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="label">OG Title</label><input type="text" name="og_title" class="input" value="{{ $entry->og_title ?? '' }}"></div>
                <div><label class="label">OG Image</label><x-admin.image-upload name="og_image" :value="$entry->og_image ?? ''" folder="seo" /></div>
            </div>
            <div><label class="label">OG Description</label><textarea name="og_description" rows="2" class="input">{{ $entry->og_description ?? '' }}</textarea></div>
            <div><label class="label">Schema JSON-LD</label><textarea name="schema_json" rows="6" class="input font-mono text-xs">{{ $entry->schema_json ?? '' }}</textarea></div>
            <div>
                <label class="label">Robots</label>
                <select name="robots" class="input">
                    @foreach (['index,follow' => 'index, follow', 'noindex,follow' => 'noindex, follow', 'noindex,nofollow' => 'noindex, nofollow'] as $k => $label)
                        <option value="{{ $k }}" @selected(($entry->robots ?? 'index,follow') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <button class="btn-primary btn-md mt-5">Save SEO</button>
    </form>
@endsection

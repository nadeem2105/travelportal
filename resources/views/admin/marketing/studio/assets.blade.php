@extends('layouts.admin')
@section('pageTitle', 'Media Library')

@section('content')
<div class="flex items-center justify-between">
    <div>
        <a href="{{ route('admin.studio.index') }}" class="text-xs text-ink-500 hover:text-brand-700">← Studio</a>
        <h1 class="font-display text-xl font-bold">Media Library</h1>
        <p class="text-xs text-ink-500">Portal, uploaded &amp; AI-generated assets. AI images are marked illustrative.</p>
    </div>
</div>

@if (session('success'))<div class="alert-success mt-3">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert-error mt-3">{{ session('error') }}</div>@endif

<div class="mt-4 grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
    {{-- Upload + filters --}}
    <div class="space-y-4">
        <form method="POST" action="{{ route('admin.studio.assets.store') }}" enctype="multipart/form-data" class="admin-card space-y-3">
            @csrf
            <h2 class="text-sm font-bold uppercase tracking-wide text-ink-500">Upload</h2>
            <input type="file" name="file" accept="image/*" class="input" required>
            <input type="text" name="name" class="input" placeholder="Name (optional)">
            <select name="category" class="input">
                <option value="image">Image</option>
                <option value="logo">Logo</option>
                <option value="banner">Banner</option>
                <option value="product">Product</option>
            </select>
            <button class="btn-primary btn-sm w-full">Upload asset</button>
        </form>

        <form method="GET" class="admin-card space-y-3">
            <h2 class="text-sm font-bold uppercase tracking-wide text-ink-500">Filter</h2>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="input" placeholder="Search name…">
            <select name="category" class="input">
                <option value="">All categories</option>
                @foreach (['image','ai_image','logo','banner','product','campaign'] as $c)
                    <option value="{{ $c }}" @selected(($filters['category'] ?? '') === $c)>{{ ucfirst(str_replace('_',' ',$c)) }}</option>
                @endforeach
            </select>
            <select name="source" class="input">
                <option value="">All sources</option>
                @foreach (['portal','ai','upload'] as $s)
                    <option value="{{ $s }}" @selected(($filters['source'] ?? '') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button class="btn-ghost btn-sm w-full">Apply</button>
        </form>
    </div>

    {{-- Grid --}}
    <div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @forelse ($assets as $a)
                <div class="admin-card !p-2">
                    <span class="block aspect-square overflow-hidden rounded-lg bg-slate-100">
                        @if ($a->thumbUrl())<img src="{{ $a->thumbUrl() }}" class="h-full w-full object-cover" alt="{{ $a->name }}" loading="lazy">@endif
                    </span>
                    <p class="mt-1 truncate text-xs font-semibold">{{ $a->name }}</p>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] text-ink-400">{{ $a->width }}×{{ $a->height }}</span>
                        @if ($a->license_status === 'illustrative')<span class="status-pill bg-amber-100 text-amber-700 !text-[9px]">Illustrative</span>@endif
                    </div>
                    <form method="POST" action="{{ route('admin.studio.assets.destroy', $a) }}" class="mt-1" onsubmit="return confirm('Delete this asset?')">@csrf @method('DELETE')
                        <input type="hidden" name="force" value="1">
                        <button class="text-[11px] text-rose-500">Delete</button>
                    </form>
                </div>
            @empty
                <p class="col-span-full py-12 text-center text-sm text-ink-400">No assets yet. Upload one or generate a creative.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $assets->links() }}</div>
    </div>
</div>
@endsection

@extends('layouts.admin')
@section('pageTitle', 'Media Library')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-xl font-bold">Media Library</h1>
        <form action="{{ route('admin.media.store') }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
            @csrf
            <input type="file" name="file" accept=".jpg,.jpeg,.png,.webp,.svg,.gif" required
                   class="text-sm file:mr-3 file:rounded-full file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-700">
            <input type="text" name="folder" class="input !w-32" placeholder="folder" value="media">
            <button class="btn-primary btn-md">Upload</button>
        </form>
    </div>

    <form method="GET" class="mt-3 flex max-w-sm gap-2">
        <input type="text" name="q" class="input" placeholder="Search files…" value="{{ request('q') }}">
        <button class="btn-ghost btn-md">Search</button>
    </form>

    <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4 xl:grid-cols-6">
        @forelse ($media as $item)
            <div class="admin-card !p-3">
                <div class="flex h-28 items-center justify-center overflow-hidden rounded-xl bg-slate-50">
                    <img src="{{ $item->url }}" alt="{{ $item->name }}" class="max-h-full max-w-full object-contain">
                </div>
                <p class="mt-2 truncate text-xs font-bold" title="{{ $item->path }}">{{ $item->name }}</p>
                <p class="text-[10px] text-ink-500">{{ $item->dimension ?? strtoupper(pathinfo($item->file_name, PATHINFO_EXTENSION)) }} · {{ number_format($item->size / 1024, 0) }} KB</p>
                <div class="mt-2 flex items-center justify-between">
                    <button type="button" class="text-[10px] font-bold text-brand-600" onclick="navigator.clipboard.writeText('{{ $item->path }}'); this.textContent = 'Copied!'">Copy Path</button>
                    <form action="{{ route('admin.media.destroy', $item) }}" method="POST" onclick="return confirm('Delete file?')">
                        @csrf @method('DELETE')
                        <button class="text-[10px] font-bold text-rose-500">Delete</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="admin-card col-span-full p-10 text-center text-ink-500">No media uploaded yet</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $media->links() }}</div>
@endsection

@extends('layouts.admin')
@section('pageTitle', 'Menus')

@section('content')
    <h1 class="font-display text-xl font-bold">Menus</h1>
    <p class="mt-1 text-sm text-ink-500">Header and footer menus. If a location has no menu, the site falls back to built-in defaults.</p>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        @forelse ($menus as $menu)
            <div class="admin-card">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-display text-base font-bold">{{ $menu->name }}</h2>
                        <p class="text-xs text-ink-500">Location: {{ $menu->location }}</p>
                    </div>
                    <form action="{{ route('admin.menus.destroy', $menu) }}" method="POST" onclick="return confirm('Delete menu?')">
                        @csrf @method('DELETE')
                        <button class="text-xs font-bold text-rose-500">Delete</button>
                    </form>
                </div>

                <div class="mt-3 space-y-1.5">
                    @forelse ($menu->items as $item)
                        <form action="{{ route('admin.menu-items.update', $item) }}" method="POST" class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-100 p-2">
                            @csrf @method('PUT')
                            <input type="text" name="label" class="input !w-32 !px-2 !py-1" value="{{ $item->label }}">
                            <input type="text" name="url" class="input flex-1 !px-2 !py-1" value="{{ $item->url }}">
                            <input type="number" name="sort_order" class="input !w-16 !px-2 !py-1" value="{{ $item->sort_order }}">
                            <select name="status" class="input !w-24 !px-2 !py-1">
                                <option value="active" @selected($item->status === 'active')>Active</option>
                                <option value="inactive" @selected($item->status === 'inactive')>Off</option>
                            </select>
                            <button class="btn-ghost btn-sm !py-1">Save</button>
                            <a href="#" onclick="event.preventDefault(); document.getElementById('del-item-{{ $item->id }}').submit()" class="text-xs font-bold text-rose-500">✕</a>
                        </form>
                        <form id="del-item-{{ $item->id }}" action="{{ route('admin.menu-items.destroy', $item) }}" method="POST" class="hidden">
                            @csrf @method('DELETE')
                        </form>
                    @empty
                        <p class="text-sm text-ink-500">No items</p>
                    @endforelse
                </div>

                <form action="{{ route('admin.menus.items', $menu) }}" method="POST" class="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                    @csrf
                    <input type="text" name="label" class="input !w-32" placeholder="Label" required>
                    <input type="text" name="url" class="input flex-1" placeholder="/packages or https://…" required>
                    <select name="target" class="input !w-28">
                        <option value="_self">Same tab</option>
                        <option value="_blank">New tab</option>
                    </select>
                    <button class="btn-ghost btn-sm">Add Item</button>
                </form>
            </div>
        @empty
            <div class="admin-card text-center text-ink-500">No menus configured</div>
        @endforelse
    </div>

    <div class="admin-card mt-4 max-w-md">
        <h2 class="font-display text-base font-bold">Create Menu</h2>
        <form action="{{ route('admin.menus.store') }}" method="POST" class="mt-3 space-y-3">
            @csrf
            <div><label class="label">Name</label><input type="text" name="name" class="input" placeholder="Main Menu" required></div>
            <div>
                <label class="label">Location</label>
                <select name="location" class="input">
                    @foreach (['header' => 'Header', 'footer_quick' => 'Footer — Quick Links', 'footer_support' => 'Footer — Support', 'footer_legal' => 'Footer — Legal'] as $k => $label)
                        <option value="{{ $k }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn-primary btn-md w-full">Create Menu</button>
        </form>
    </div>
@endsection

@extends('layouts.admin')
@section('pageTitle', 'Brand Kits')

@section('content')
<div class="flex items-center justify-between" x-data="{ open: false, editing: null }" @edit-kit.window="editing = $event.detail; open = true">
    <div>
        <a href="{{ route('admin.studio.index') }}" class="text-xs text-ink-500 hover:text-brand-700">← Studio</a>
        <h1 class="font-display text-xl font-bold">Brand Kits</h1>
        <p class="text-xs text-ink-500">Every creative follows the selected kit — logo, colours, contact, CTA &amp; disclaimer.</p>
    </div>
    <button class="btn-primary btn-sm" @click="editing = null; open = true">＋ New Brand Kit</button>

    {{-- Modal --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink-900/50" @click="open = false"></div>
        <div class="relative z-10 w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-float" style="max-height:90vh">
            <h2 class="font-display text-lg font-bold" x-text="editing ? 'Edit Brand Kit' : 'New Brand Kit'"></h2>
            <form :action="editing ? `{{ url('admin/studio/brand-kits') }}/${editing.id}` : '{{ route('admin.studio.brand-kits.store') }}'" method="POST" class="mt-4 grid gap-3 sm:grid-cols-2">
                @csrf
                <template x-if="editing">@method('PUT')</template>
                <div class="sm:col-span-2"><label class="label">Name</label><input name="name" class="input" :value="editing?.name" required></div>
                <div><label class="label">Brand name</label><input name="brand_name" class="input" :value="editing?.brand_name"></div>
                <div><label class="label">Logo path <span class="text-ink-400">(storage/asset path)</span></label><input name="logo_path" class="input" :value="editing?.logo_path" placeholder="images/logo.svg"></div>
                <div><label class="label">Primary colour</label><input type="color" name="primary_color" class="input h-10" :value="editing?.primary_color || '#2563eb'"></div>
                <div><label class="label">Secondary colour</label><input type="color" name="secondary_color" class="input h-10" :value="editing?.secondary_color || '#0b1f3a'"></div>
                <div><label class="label">Accent colour</label><input type="color" name="accent_color" class="input h-10" :value="editing?.accent_color || '#f59e0b'"></div>
                <div><label class="label">Text colour</label><input type="color" name="text_color" class="input h-10" :value="editing?.text_color || '#ffffff'"></div>
                <div><label class="label">Phone</label><input name="phone" class="input" :value="editing?.phone"></div>
                <div><label class="label">WhatsApp</label><input name="whatsapp" class="input" :value="editing?.whatsapp"></div>
                <div><label class="label">Website</label><input name="website" class="input" :value="editing?.website"></div>
                <div><label class="label">Email</label><input name="email" class="input" :value="editing?.email"></div>
                <div><label class="label">Default CTA</label><input name="default_cta" class="input" :value="editing?.default_cta || 'Book Now'"></div>
                <div><label class="label">Default disclaimer</label><input name="default_disclaimer" class="input" :value="editing?.default_disclaimer"></div>
                <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="is_default" value="1" x-bind:checked="editing?.is_default" class="rounded border-slate-300"> Set as default kit</label>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" class="btn-ghost btn-md" @click="open = false">Cancel</button>
                    <button class="btn-primary btn-md">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if (session('success'))<div class="alert-success mt-3">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert-error mt-3">{{ session('error') }}</div>@endif

<div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" x-data>
    @foreach ($kits as $kit)
        <div class="admin-card">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    @if ($kit->logoUrl())<img src="{{ $kit->logoUrl() }}" class="h-8 w-8 rounded object-contain" alt="">@endif
                    <div>
                        <p class="text-sm font-bold">{{ $kit->name }}</p>
                        <p class="text-xs text-ink-500">{{ $kit->brand_name }}</p>
                    </div>
                </div>
                @if ($kit->is_default)<span class="status-pill bg-brand-100 text-brand-700">Default</span>@endif
            </div>
            <div class="mt-3 flex gap-1">
                <span class="h-6 w-6 rounded" style="background: {{ $kit->primary_color }}"></span>
                <span class="h-6 w-6 rounded" style="background: {{ $kit->secondary_color }}"></span>
                <span class="h-6 w-6 rounded" style="background: {{ $kit->accent_color }}"></span>
            </div>
            <div class="mt-3 flex gap-2">
                <button class="btn-ghost btn-sm"
                        @click="$dispatch('edit-kit', {{ Illuminate\Support\Js::from($kit->only(['id','name','brand_name','logo_path','primary_color','secondary_color','accent_color','text_color','phone','whatsapp','website','email','default_cta','default_disclaimer','is_default'])) }})">Edit</button>
                @unless ($kit->is_default)
                    <form method="POST" action="{{ route('admin.studio.brand-kits.destroy', $kit) }}" onsubmit="return confirm('Delete this brand kit?')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-rose-600">Delete</button></form>
                @endunless
            </div>
        </div>
    @endforeach
</div>

@endsection

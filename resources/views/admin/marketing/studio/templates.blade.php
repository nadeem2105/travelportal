@extends('layouts.admin')
@section('pageTitle', 'Creative Templates')

@section('content')
<div class="flex items-center justify-between" x-data="{ open: false }">
    <div>
        <a href="{{ route('admin.studio.index') }}" class="text-xs text-ink-500 hover:text-brand-700">← Studio</a>
        <h1 class="font-display text-xl font-bold">Creative Templates</h1>
        @php $tokenHint = 'Use tokens like {{package_name}}, {{destination}}, {{price}}, {{discount}}, {{phone}}, {{website}}.'; @endphp
        <p class="text-xs text-ink-500">Dynamic templates. {{ $tokenHint }}</p>
    </div>
    <button class="btn-primary btn-sm" @click="open = true">＋ New Template</button>

    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink-900/50" @click="open = false"></div>
        <div class="relative z-10 w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-float" style="max-height:90vh">
            <h2 class="font-display text-lg font-bold">New Template</h2>
            <form method="POST" action="{{ route('admin.studio.templates.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                @csrf
                <div class="sm:col-span-2"><label class="label">Name</label><input name="name" class="input" required></div>
                <div><label class="label">Category</label>
                    <select name="category" class="input">
                        <option value="package">Travel Package</option>
                        <option value="hotel">Hotel</option>
                        <option value="promotion">Promotion</option>
                        <option value="social">Social</option>
                    </select>
                </div>
                <div><label class="label">Subcategory</label><input name="subcategory" class="input" placeholder="honeymoon / flash_sale / reel"></div>
                <div><label class="label">Platform</label>
                    <select name="platform" class="input"><option value="">Any</option>@foreach ($platforms as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                </div>
                <div><label class="label">Style</label>
                    <select name="style" class="input"><option value="">Any</option>@foreach ($styles as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                </div>
                @php
                    $phHeadline = '{{destination}} — {{duration}} from {{price}}';
                    $phPrimary = 'Discover {{package_name}}. {{discount}} · Call {{phone}}';
                    $phPrompt = 'Cinematic travel photo of {{destination}}, golden hour…';
                @endphp
                <div class="sm:col-span-2"><label class="label">Headline template</label><input name="headline_template" class="input" placeholder="{{ $phHeadline }}"></div>
                <div class="sm:col-span-2"><label class="label">Primary text template</label><textarea name="primary_text_template" rows="2" class="input" placeholder="{{ $phPrimary }}"></textarea></div>
                <div class="sm:col-span-2"><label class="label">AI image prompt scaffold</label><textarea name="image_prompt_template" rows="2" class="input" placeholder="{{ $phPrompt }}"></textarea></div>
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

<div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($templates as $t)
        <div class="admin-card">
            <div class="flex items-center justify-between">
                <p class="text-sm font-bold">{{ $t->name }}</p>
                <span class="status-pill bg-slate-100 text-ink-600">{{ ucfirst($t->category) }}</span>
            </div>
            @if ($t->headline_template)<p class="mt-2 text-xs text-ink-500">{{ $t->headline_template }}</p>@endif
            <div class="mt-3 flex gap-2">
                @unless ($t->is_system)
                    <form method="POST" action="{{ route('admin.studio.templates.destroy', $t) }}" onsubmit="return confirm('Delete this template?')">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-rose-600">Delete</button></form>
                @else
                    <span class="text-xs text-ink-400">System template</span>
                @endunless
            </div>
        </div>
    @empty
        <p class="col-span-full py-10 text-center text-sm text-ink-400">No templates yet.</p>
    @endforelse
</div>

<div class="mt-4">{{ $templates->links() }}</div>
@endsection

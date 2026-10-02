@extends('layouts.admin')
@section('pageTitle', $group->exists ? 'Edit Group' : 'New Group')

@section('content')
<a href="{{ route('admin.contact-groups.index') }}" class="text-sm text-ink-500 hover:text-brand-700">← All Groups</a>
<h1 class="mt-2 font-display text-xl font-bold">{{ $group->exists ? 'Edit Group' : 'New Contact Group' }}</h1>

@if ($errors->any())
    <div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<form action="{{ $group->exists ? route('admin.contact-groups.update', $group) : route('admin.contact-groups.store') }}"
      method="POST" class="admin-card mt-4 max-w-2xl space-y-4 p-6"
      x-data="{ type: '{{ old('type', $group->type) }}' }">
    @csrf
    @if ($group->exists) @method('PUT') @endif

    <div>
        <label class="label">Group Name *</label>
        <input type="text" name="name" value="{{ old('name', $group->name) }}" required class="input" placeholder="e.g. Kashmir enquiries – opted in">
    </div>

    <div>
        <label class="label">Description</label>
        <textarea name="description" rows="2" class="input">{{ old('description', $group->description) }}</textarea>
    </div>

    <div>
        <label class="label">Type *</label>
        <div class="flex gap-4 text-sm">
            <label class="inline-flex items-center gap-2"><input type="radio" name="type" value="static" x-model="type"> Static (manually add contacts)</label>
            <label class="inline-flex items-center gap-2"><input type="radio" name="type" value="dynamic" x-model="type"> Dynamic (auto by filter)</label>
        </div>
    </div>

    {{-- Dynamic filters --}}
    <div x-show="type === 'dynamic'" x-cloak class="space-y-4 rounded-lg border bg-ink-50 p-4">
        <p class="text-xs text-ink-500">Members are resolved automatically from contacts matching all of the filters below.</p>
        @php $f = old('filter_lifecycle_stage', $group->filters['lifecycle_stage'] ?? ''); @endphp
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Lifecycle stage</label>
                <select name="filter_lifecycle_stage" class="input">
                    <option value="">Any</option>
                    @foreach ($lifecycleStages as $s)<option value="{{ $s }}" @selected($f === $s)>{{ label_case($s) }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label">Source</label>
                <select name="filter_source_id" class="input">
                    <option value="">Any</option>
                    @foreach ($sources as $src)<option value="{{ $src->id }}" @selected((string) old('filter_source_id', $group->filters['source_id'] ?? '') === (string) $src->id)>{{ $src->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label">Tag</label>
                <select name="filter_tag_id" class="input">
                    <option value="">Any</option>
                    @foreach ($tags as $tag)<option value="{{ $tag->id }}" @selected((string) old('filter_tag_id', $group->filters['tag_id'] ?? '') === (string) $tag->id)>{{ $tag->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label">WhatsApp opt-in</label>
                @php $optIn = old('filter_whatsapp_opt_in', $group->filters['whatsapp_opt_in'] ?? ''); @endphp
                <select name="filter_whatsapp_opt_in" class="input">
                    <option value="" @selected($optIn === '')>Any</option>
                    <option value="1" @selected((string) $optIn === '1')>Opted in</option>
                    <option value="0" @selected((string) $optIn === '0')>Not opted in</option>
                </select>
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-2 border-t pt-4">
        <a href="{{ route('admin.contact-groups.index') }}" class="btn-ghost btn-sm">Cancel</a>
        <button class="btn-primary btn-sm">{{ $group->exists ? 'Update Group' : 'Create Group' }}</button>
    </div>
</form>
@endsection

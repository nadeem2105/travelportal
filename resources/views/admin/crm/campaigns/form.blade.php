@extends('layouts.admin')
@section('pageTitle', 'New Campaign')

@section('content')
<a href="{{ route('admin.whatsapp-campaigns.index') }}" class="text-sm text-ink-500 hover:text-brand-700">← All Campaigns</a>
<h1 class="mt-2 font-display text-xl font-bold">New WhatsApp Campaign</h1>

@if ($errors->any())
    <div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@if ($templates->isEmpty())
    <div class="mt-3 rounded-lg bg-amber-50 px-4 py-2 text-sm text-amber-800">
        No approved templates found. <a href="{{ route('admin.whatsapp-templates.index') }}" class="font-semibold underline">Sync your templates from Meta</a> first — campaigns can only send approved templates.
    </div>
@endif

@php
    $tplData = $templates->map(fn ($t) => [
        'name' => $t->name, 'lang' => $t->language, 'vars' => $t->body_variable_count, 'preview' => $t->body_preview,
    ])->values();
@endphp

<form action="{{ route('admin.whatsapp-campaigns.store') }}" method="POST"
      class="admin-card mt-4 max-w-2xl space-y-4 p-6"
      x-data='{
        templates: @json($tplData),
        selected: @json(old("template_name", "")),
        get current() { return this.templates.find(t => t.name === this.selected) || null; },
        get varCount() { return this.current ? this.current.vars : 0; },
      }'>
    @csrf

    <div>
        <label class="label">Campaign Name *</label>
        <input type="text" name="name" value="{{ old('name') }}" required class="input" placeholder="e.g. Diwali Kashmir offer">
    </div>

    <div>
        <label class="label">Audience (Contact Group) *</label>
        <select name="contact_group_id" required class="input">
            <option value="">Select a group…</option>
            @foreach ($groups as $g)
                <option value="{{ $g->id }}" @selected((string) old('contact_group_id') === (string) $g->id)>{{ $g->name }} ({{ ucfirst($g->type) }})</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-ink-400">Only WhatsApp-reachable, opted-in contacts in the group receive the message.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_140px]">
        <div>
            <label class="label">Template *</label>
            <select name="template_name" required class="input" x-model="selected"
                    @change="$refs.lang.value = (current ? current.lang : 'en_US')">
                <option value="">Select an approved template…</option>
                @foreach ($templates as $t)
                    <option value="{{ $t->name }}">{{ $t->name }} ({{ $t->language }}) · {{ $t->body_variable_count }} vars</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Language</label>
            <input type="text" name="template_language" x-ref="lang" value="{{ old('template_language', 'en_US') }}" class="input">
        </div>
    </div>

    <template x-if="current && current.preview">
        <div class="rounded-lg border bg-ink-50 p-3 text-xs text-ink-600">
            <span class="font-semibold">Preview:</span> <span x-text="current.preview"></span>
        </div>
    </template>

    {{-- Body variables, rendered to match the selected template's placeholder count --}}
    <div x-show="varCount > 0" x-cloak class="space-y-2">
        <label class="label">Template body variables</label>
        <template x-for="i in varCount" :key="i">
            <input type="text" name="template_params[]" class="input" :placeholder="'Variable ' + i"></input>
        </template>
        <p class="text-xs text-ink-400">These values are applied to every recipient. Personalization tokens can be added in a later pass.</p>
    </div>

    <div class="flex justify-end gap-2 border-t pt-4">
        <a href="{{ route('admin.whatsapp-campaigns.index') }}" class="btn-ghost btn-sm">Cancel</a>
        <button class="btn-primary btn-sm">Save as Draft</button>
    </div>
</form>
@endsection

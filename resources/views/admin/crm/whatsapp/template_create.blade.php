@extends('layouts.admin')
@section('pageTitle', 'Create WhatsApp Template')

@section('content')
<a href="{{ route('admin.whatsapp-templates.index') }}" class="text-sm text-ink-500 hover:text-brand-700">← All Templates</a>
<h1 class="mt-2 font-display text-xl font-bold">Create WhatsApp Template</h1>
<p class="text-xs text-ink-500">Submitted to Meta for approval. Once approved it becomes usable for notifications, campaigns and auto-replies.</p>

@if (session('error'))<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ session('error') }}</div>@endif
@if ($errors->any())<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

{{-- Quick-fill presets --}}
<div class="admin-card mt-4 p-4">
    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-500">Quick start — recommended templates the app uses</p>
    <div class="flex flex-wrap gap-2">
        @foreach ($presets as $key => $p)
            <a href="{{ route('admin.whatsapp-templates.create', ['preset' => $key]) }}" class="btn-ghost btn-xs">{{ $p['label'] }}</a>
        @endforeach
    </div>
</div>

@php
    $initBody = old('body', $preset['body'] ?? '');
    $initExamples = old('examples', $preset['examples'] ?? []);
@endphp

<form action="{{ route('admin.whatsapp-templates.store') }}" method="POST"
      class="admin-card mt-4 max-w-2xl space-y-4 p-6"
      x-data='{
        body: @json($initBody),
        examples: @json(array_values($initExamples)),
        get varCount() { const m = this.body.match(/\{\{\s*(\d+)\s*\}\}/g); if (!m) return 0; return Math.max(...m.map(x => parseInt(x.replace(/\D/g, "")))); },
        sync() { const n = this.varCount; while (this.examples.length < n) this.examples.push(""); this.examples.length = n; }
      }'
      x-init="sync()">
    @csrf

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_160px_120px]">
        <div>
            <label class="label">Template name *</label>
            <input type="text" name="name" value="{{ old('name', request('preset')) }}" required class="input" placeholder="booking_confirmed">
            <p class="mt-1 text-[11px] text-ink-400">Lowercase letters, numbers, underscores only.</p>
        </div>
        <div>
            <label class="label">Category *</label>
            <select name="category" class="input">
                @foreach (['UTILITY', 'MARKETING', 'AUTHENTICATION'] as $cat)
                    <option value="{{ $cat }}" @selected(old('category', $preset['category'] ?? 'UTILITY') === $cat)>{{ ucfirst(strtolower($cat)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Language</label>
            <input type="text" name="language" value="{{ old('language', 'en_US') }}" class="input">
        </div>
    </div>

    <div>
        <label class="label">Header</label>
        <select name="header_type" class="input">
            <option value="none" @selected(old('header_type', $preset['header_type'] ?? 'none') === 'none')>None (text only)</option>
            <option value="document" @selected(old('header_type', $preset['header_type'] ?? 'none') === 'document')>Document (PDF attachment)</option>
        </select>
        <p class="mt-1 text-[11px] text-ink-400">Choose "Document" to attach a PDF (quote/invoice) automatically. A sample PDF is submitted to Meta for review.</p>
    </div>

    <div>
        <label class="label">Message body *</label>
        <textarea name="body" x-model="body" @input="sync()" rows="5" required class="input font-mono text-sm"
                  placeholder="Hi there, your booking is confirmed. Use variables like the ones shown below."></textarea>
        <p class="mt-1 text-[11px] text-ink-400">Use <span class="font-mono">&#123;&#123;1&#125;&#125;</span>, <span class="font-mono">&#123;&#123;2&#125;&#125;</span>… for variables. Don't start/end with a variable or place two back-to-back (Meta rejects those).</p>
    </div>

    {{-- Example values, one per variable (Meta requires these for approval) --}}
    <div x-show="varCount > 0" x-cloak class="space-y-2 rounded-lg border bg-ink-50 p-4">
        <p class="text-xs font-semibold text-ink-600">Sample values (used only for Meta's review)</p>
        <template x-for="i in varCount" :key="i">
            <div class="flex items-center gap-2">
                <span class="w-14 font-mono text-xs text-ink-400" x-text="'var ' + i"></span>
                <input type="text" name="examples[]" x-model="examples[i-1]" class="input flex-1 text-sm" :placeholder="'Sample for variable ' + i">
            </div>
        </template>
    </div>

    <div class="flex justify-end gap-2 border-t pt-4">
        <a href="{{ route('admin.whatsapp-templates.index') }}" class="btn-ghost btn-sm">Cancel</a>
        <button class="btn-primary btn-sm">Submit for Approval</button>
    </div>
</form>
@endsection

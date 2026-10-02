@extends('layouts.admin')
@section('pageTitle', $creative->name)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <a href="{{ route('admin.studio.index') }}" class="text-xs text-ink-500 hover:text-brand-700">← Studio</a>
        <h1 class="font-display text-xl font-bold">{{ $creative->name }}</h1>
        <p class="text-xs text-ink-500">
            {{ ucfirst($creative->platform) }} · {{ \App\Services\Marketing\Creative\CreativeFormats::label($creative->format) }}
            · {{ \App\Services\Marketing\Creative\CreativeFormats::OBJECTIVES[$creative->objective] ?? $creative->objective }}
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <form method="POST" action="{{ route('admin.studio.regenerate', $creative) }}">@csrf<button class="btn-ghost btn-sm">Regenerate</button></form>
        <form method="POST" action="{{ route('admin.studio.duplicate', $creative) }}">@csrf<button class="btn-ghost btn-sm">Duplicate</button></form>
        @if ($creative->render_path)
            <a href="{{ route('admin.studio.download', $creative) }}" class="btn-primary btn-sm">Download</a>
        @endif
    </div>
</div>

@if (session('success'))<div class="alert-success mt-3">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert-error mt-3">{{ session('error') }}</div>@endif

<div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
    {{-- Preview --}}
    <div class="admin-card" x-data="creativePreview()" x-init="poll()">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-bold uppercase tracking-wide text-ink-500">Preview</h2>
            <span class="status-pill" :class="pillClass" x-text="statusLabel"></span>
        </div>
        <div class="mt-3 flex items-center justify-center rounded-xl bg-slate-100 p-3" style="min-height:280px">
            <template x-if="preview">
                <img :src="preview" class="max-h-[70vh] w-auto rounded-lg shadow" alt="Creative preview">
            </template>
            <template x-if="!preview && status !== 'failed'">
                <div class="animate-pulse text-center text-sm text-ink-400">
                    <div class="mx-auto mb-3 h-10 w-10 rounded-full border-2 border-brand-300 border-t-brand-600" style="animation:spin 1s linear infinite"></div>
                    Preparing your creative…
                </div>
            </template>
            <template x-if="!preview && status === 'failed'">
                <div class="text-center text-sm text-rose-600">
                    <p x-text="error || 'Generation failed.'"></p>
                    <form method="POST" action="{{ route('admin.studio.regenerate', $creative) }}" class="mt-3">@csrf<button class="btn-primary btn-sm">Retry</button></form>
                </div>
            </template>
        </div>

        {{-- Fact-check / quality warnings --}}
        <template x-if="warnings.length">
            <div class="mt-3 rounded-xl bg-amber-50 p-3 text-xs text-amber-800 ring-1 ring-amber-200">
                <p class="font-bold">Review before publishing:</p>
                <ul class="mt-1 list-disc pl-4">
                    <template x-for="w in warnings" :key="w"><li x-text="w"></li></template>
                </ul>
            </div>
        </template>
    </div>

    {{-- Side panel --}}
    <div class="space-y-4">
        {{-- Copy --}}
        <div class="admin-card">
            <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-ink-500">Ad Copy</h2>
            <p class="text-xs text-ink-400">Headline</p>
            <p class="text-sm font-semibold">{{ $creative->headline ?: '—' }}</p>
            @if (!empty($creative->copy['primary_text']))
                <p class="mt-2 text-xs text-ink-400">Primary text</p>
                <p class="text-sm">{{ $creative->copy['primary_text'] }}</p>
            @endif
            @if (!empty($creative->copy['description']))
                <p class="mt-2 text-xs text-ink-400">Description</p>
                <p class="text-sm">{{ $creative->copy['description'] }}</p>
            @endif
            <p class="mt-2 text-xs text-ink-400">CTA</p>
            <p class="text-sm font-semibold">{{ $creative->copy['cta'] ?? '—' }}</p>
        </div>

        {{-- Facts (source of truth) --}}
        <div class="admin-card">
            <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-ink-500">Live Facts</h2>
            <dl class="space-y-1 text-sm">
                @foreach ([
                    'Destination' => $creative->spec['facts']['destination'] ?? null,
                    'Duration' => $creative->spec['facts']['duration'] ?? null,
                    'Price' => $creative->spec['facts']['price_formatted'] ?? null,
                    'Discount' => $creative->spec['facts']['discount'] ?? null,
                    'Hotel' => $creative->spec['facts']['hotel_name'] ?? null,
                ] as $k => $v)
                    @if ($v)<div class="flex justify-between"><dt class="text-ink-500">{{ $k }}</dt><dd class="font-semibold">{{ $v }}</dd></div>@endif
                @endforeach
            </dl>
        </div>

        {{-- Tracking --}}
        @if (!empty($creative->tracking['tracked_url']))
            <div class="admin-card">
                <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-ink-500">Tracking</h2>
                <p class="text-xs text-ink-400">Creative code</p>
                <p class="font-mono text-xs">{{ $creative->tracking['creative_code'] ?? '' }}</p>
                <p class="mt-2 text-xs text-ink-400">UTM landing URL</p>
                <p class="break-all font-mono text-[11px] text-ink-600">{{ $creative->tracking['tracked_url'] }}</p>
            </div>
        @endif

        {{-- Approval workflow --}}
        <div class="admin-card">
            <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-ink-500">Approval</h2>
            <p class="mb-2"><span class="status-pill {{ $creative->approval_status === 'approved' || $creative->approval_status === 'published' ? 'bg-emerald-100 text-emerald-700' : ($creative->approval_status === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-ink-600') }}">{{ ucfirst($creative->approval_status) }}</span></p>
            @if ($creative->approval_note)<p class="mb-2 text-xs text-rose-600">Note: {{ $creative->approval_note }}</p>@endif
            <div class="flex flex-wrap gap-2">
                @if (in_array($creative->approval_status, ['draft', 'rejected']))
                    <form method="POST" action="{{ route('admin.studio.submit', $creative) }}">@csrf<button class="btn-ghost btn-sm">Submit for approval</button></form>
                @endif
                @if ($creative->approval_status === 'pending')
                    <form method="POST" action="{{ route('admin.studio.approve', $creative) }}">@csrf<button class="btn-primary btn-sm">Approve</button></form>
                    <form method="POST" action="{{ route('admin.studio.reject', $creative) }}" class="flex gap-1">@csrf
                        <input name="note" class="input !py-1 text-xs" placeholder="Reason">
                        <button class="btn-ghost btn-sm">Reject</button>
                    </form>
                @endif
                @if ($creative->approval_status === 'approved')
                    <form method="POST" action="{{ route('admin.studio.publish', $creative) }}">@csrf<button class="btn-primary btn-sm">Mark Published</button></form>
                @endif
            </div>
        </div>

        {{-- Variations --}}
        <div class="admin-card">
            <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-ink-500">Generate Variations</h2>
            <form method="POST" action="{{ route('admin.studio.variations', $creative) }}">
                @csrf
                <div class="grid grid-cols-1 gap-1 text-sm">
                    @foreach ($focuses as $val => $lbl)
                        <label class="flex items-center gap-2"><input type="checkbox" name="focuses[]" value="{{ $val }}" class="rounded border-slate-300">{{ $lbl }}</label>
                    @endforeach
                </div>
                <button class="btn-primary btn-sm mt-3 w-full">Generate selected</button>
            </form>
        </div>

        @if (! in_array($creative->approval_status, ['published']))
            <form method="POST" action="{{ route('admin.studio.destroy', $creative) }}" onsubmit="return confirm('Delete this creative?')">
                @csrf @method('DELETE')
                <button class="btn-ghost btn-sm w-full text-rose-600">Delete creative</button>
            </form>
        @endif
    </div>
</div>

@if ($siblings->count())
    <h2 class="mt-6 font-display text-lg font-bold">Variations &amp; formats in this set</h2>
    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($siblings as $s)
            <a href="{{ route('admin.studio.show', $s) }}" class="admin-card !p-2">
                <span class="block aspect-square overflow-hidden rounded-lg bg-slate-100">
                    @if ($s->previewUrl())<img src="{{ $s->previewUrl() }}" class="h-full w-full object-cover" alt="" loading="lazy">@endif
                </span>
                <span class="mt-1 block truncate text-xs font-semibold">{{ $s->variation_focus ? ucfirst($s->variation_focus) : \App\Services\Marketing\Creative\CreativeFormats::label($s->format) }}</span>
            </a>
        @endforeach
    </div>
@endif

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('creativePreview', () => ({
        status: @json($creative->generation_status),
        preview: @json($creative->previewUrl()),
        warnings: @json($creative->warnings ?? []),
        error: @json($creative->generation_error),
        get statusLabel() {
            return { queued: 'Queued', processing: 'Generating…', completed: 'Ready', failed: 'Failed', cancelled: 'Cancelled' }[this.status] || this.status;
        },
        get pillClass() {
            return this.status === 'completed' ? 'bg-emerald-100 text-emerald-700'
                : this.status === 'failed' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700';
        },
        poll() {
            if (['completed', 'failed', 'cancelled'].includes(this.status)) return;
            const url = @json(route('admin.studio.status', $creative));
            const timer = setInterval(async () => {
                try {
                    const { data } = await window.axios.get(url);
                    this.status = data.generation_status;
                    this.preview = data.preview;
                    this.warnings = data.warnings || [];
                    this.error = data.error;
                    if (['completed', 'failed', 'cancelled'].includes(this.status)) clearInterval(timer);
                } catch (e) { clearInterval(timer); }
            }, 3000);
        },
    }));
});
</script>
@endpush
@endsection

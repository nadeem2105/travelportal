@extends('layouts.admin')
@section('pageTitle', 'Ad Creative Studio')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-xl font-bold">Ad Creative Studio</h1>
        <p class="text-xs text-ink-500">Turn any package into a ready-to-publish ad campaign — real data, AI copy &amp; visuals, brand-perfect.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.studio.create') }}" class="btn-primary btn-sm">＋ Create Creative</a>
        <a href="{{ route('admin.studio.brand-kits.index') }}" class="btn-ghost btn-sm">Brand Kits</a>
        <a href="{{ route('admin.studio.templates.index') }}" class="btn-ghost btn-sm">Templates</a>
        <a href="{{ route('admin.studio.assets.index') }}" class="btn-ghost btn-sm">Media Library</a>
    </div>
</div>

@if (session('success'))<div class="alert-success mt-3">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert-error mt-3">{{ session('error') }}</div>@endif

@unless ($imageReady)
    <div class="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
        AI image generation is not connected. Creatives will use your portal images + brand overlay. Enable it on the
        <a href="{{ route('admin.ai-settings.index') }}" class="underline">AI Providers</a> page (set an OpenAI key and turn on image generation).
    </div>
@endunless

{{-- KPIs --}}
<div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
    @foreach ([
        ['Campaigns', $stats['campaigns']],
        ['Active Campaigns', $stats['active_campaigns']],
        ['Total Creatives', $stats['creatives']],
        ['Draft Creatives', $stats['drafts']],
        ['Published', $stats['published']],
    ] as [$label, $val])
        <div class="admin-card">
            <p class="text-xs text-ink-500">{{ $label }}</p>
            <p class="mt-1 font-display text-2xl font-extrabold">{{ number_format($val) }}</p>
        </div>
    @endforeach
</div>

{{-- Quick actions --}}
<div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    @foreach ([
        ['Create Image Ad', route('admin.studio.create')],
        ['Generate Ad Copy', route('admin.studio.create')],
        ['Create Social Post', route('admin.studio.create')],
        ['Create Campaign', route('admin.marketing.campaigns.create')],
        ['Templates', route('admin.studio.templates.index')],
        ['Media Library', route('admin.studio.assets.index')],
    ] as [$label, $url])
        <a href="{{ $url }}" class="admin-card flex items-center justify-center text-center text-sm font-semibold text-ink-700 hover:text-brand-700 hover:ring-brand-200">{{ $label }}</a>
    @endforeach
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    {{-- Recent creatives --}}
    <div class="admin-card">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-bold uppercase tracking-wide text-ink-500">Recent Creatives</h2>
            <a href="{{ route('admin.studio.create') }}" class="text-xs font-semibold text-brand-700">New</a>
        </div>
        @forelse ($recentCreatives as $c)
            <a href="{{ route('admin.studio.show', $c) }}" class="mb-2 flex items-center gap-3 rounded-xl p-2 hover:bg-slate-50">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100">
                    @if ($c->previewUrl())
                        <img src="{{ $c->previewUrl() }}" class="h-full w-full object-cover" alt="" loading="lazy">
                    @else
                        <span class="text-[10px] text-ink-400">{{ strtoupper(substr($c->generation_status,0,4)) }}</span>
                    @endif
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold">{{ $c->name }}</span>
                    <span class="block text-xs text-ink-500">{{ ucfirst($c->platform) }} · {{ \App\Services\Marketing\Creative\CreativeFormats::label($c->format) }}</span>
                </span>
                <span class="status-pill {{ $c->generation_status === 'completed' ? 'bg-emerald-100 text-emerald-700' : ($c->generation_status === 'failed' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') }}">{{ ucfirst($c->generation_status) }}</span>
            </a>
        @empty
            <p class="py-8 text-center text-sm text-ink-400">No creatives yet. <a href="{{ route('admin.studio.create') }}" class="text-brand-700 underline">Create your first</a>.</p>
        @endforelse
    </div>

    {{-- Recent campaigns --}}
    <div class="admin-card">
        <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-ink-500">Recent Campaigns</h2>
        @forelse ($recentCampaigns as $camp)
            <a href="{{ route('admin.marketing.campaigns.show', $camp) }}" class="mb-2 flex items-center justify-between rounded-xl p-2 text-sm hover:bg-slate-50">
                <span class="min-w-0 flex-1 truncate font-semibold">{{ $camp->name }}</span>
                <span class="text-xs text-ink-500">{{ ucfirst($camp->provider) }} · {{ ucfirst($camp->status) }}</span>
            </a>
        @empty
            <p class="py-8 text-center text-sm text-ink-400">No campaigns yet.</p>
        @endforelse
    </div>
</div>
@endsection

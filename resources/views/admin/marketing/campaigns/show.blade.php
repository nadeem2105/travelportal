@extends('layouts.admin')
@section('pageTitle', $campaign->name)

@php
    $inr = fn ($n) => $n === null ? '—' : '₹' . number_format((float) $n, 0);
    $statusPill = [
        'draft' => 'bg-slate-100 text-slate-600', 'published' => 'bg-sky-100 text-sky-700',
        'active' => 'bg-emerald-100 text-emerald-700', 'paused' => 'bg-amber-100 text-amber-700',
        'completed' => 'bg-indigo-100 text-indigo-700', 'rejected' => 'bg-rose-100 text-rose-700',
    ];
@endphp

@section('content')
<a href="{{ route('admin.marketing.campaigns.index') }}" class="text-sm text-ink-500 hover:text-brand-700">← Campaigns</a>

<div class="mt-2 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="font-display text-xl font-bold">{{ $campaign->name }}</h1>
        <div class="mt-1 flex items-center gap-2 text-xs text-ink-500">
            <span class="capitalize">{{ str_replace('_', ' ', $campaign->provider) }}</span>
            <span>·</span>
            <span class="status-pill capitalize {{ $statusPill[$campaign->status] ?? 'bg-slate-100' }}">{{ $campaign->status }}</span>
            @if ($campaign->external_status)<span>· platform: {{ $campaign->external_status }}</span>@endif
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if ($campaign->status === 'draft')
            <form action="{{ route('admin.marketing.campaigns.publish', $campaign) }}" method="POST" onsubmit="return confirm('Publish to the provider? It will be created PAUSED — no spend until you activate it.');">
                @csrf
                <button class="btn-primary btn-sm" @disabled(! empty($issues))>Publish (paused)</button>
            </form>
        @elseif (in_array($campaign->status, ['published', 'paused']))
            <form action="{{ route('admin.marketing.campaigns.activate', $campaign) }}" method="POST" onsubmit="return confirm('Activate this campaign? It will go LIVE and may start spending budget.');">
                @csrf
                <button class="btn-primary btn-sm bg-emerald-600 hover:bg-emerald-700">Activate (go live)</button>
            </form>
        @endif
        @if ($campaign->status === 'active')
            <form action="{{ route('admin.marketing.campaigns.pause', $campaign) }}" method="POST">
                @csrf
                <button class="btn-ghost btn-sm">Pause</button>
            </form>
        @endif
    </div>
</div>

@if (session('success'))<div class="mt-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('success') }}</div>@endif
@if (session('error'))<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ session('error') }}</div>@endif

@if (! empty($issues))
    <div class="mt-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <p class="font-semibold">Resolve before publishing:</p>
        <ul class="mt-1 list-disc pl-5">@foreach ($issues as $i)<li>{{ $i }}</li>@endforeach</ul>
    </div>
@endif

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="admin-card p-5 lg:col-span-2">
        <h2 class="font-semibold">Details</h2>
        <dl class="mt-3 grid grid-cols-2 gap-y-3 text-sm">
            <dt class="text-ink-500">Objective</dt><dd>{{ $campaign->objective ?? '—' }}</dd>
            <dt class="text-ink-500">Destination</dt><dd>{{ $campaign->destination ?? '—' }}</dd>
            <dt class="text-ink-500">Ad account</dt><dd>{{ $campaign->account->account_name ?? '— not linked —' }}</dd>
            <dt class="text-ink-500">Budget type</dt><dd class="capitalize">{{ $campaign->budget_type ?? '—' }}</dd>
            <dt class="text-ink-500">Daily budget</dt><dd>{{ $inr($campaign->daily_budget) }}</dd>
            <dt class="text-ink-500">Lifetime budget</dt><dd>{{ $inr($campaign->lifetime_budget) }}</dd>
            <dt class="text-ink-500">Target CPL</dt><dd>{{ $inr($campaign->target_cpl) }}</dd>
            <dt class="text-ink-500">External ID</dt><dd class="font-mono text-xs">{{ $campaign->external_campaign_id ?? '—' }}</dd>
        </dl>

        @if ($campaign->landing_page)
            <div class="mt-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-ink-400">Landing page</div>
                <a href="{{ $campaign->landing_page }}" target="_blank" class="text-sm text-brand-600 hover:underline">{{ $campaign->landing_page }}</a>
            </div>
        @endif
        @if ($trackingUrl)
            <div class="mt-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-ink-400">Tracking URL (UTM-tagged)</div>
                <code class="mt-1 block break-all rounded bg-ink-50 px-2 py-1 text-xs">{{ $trackingUrl }}</code>
            </div>
        @endif

        @php $meta = (array) ($campaign->meta ?? []); $adset = $meta['adset'] ?? []; $creative = $meta['creative'] ?? []; $metaIds = $meta['meta_ids'] ?? []; @endphp
        @if ($adset || $creative)
            <div class="mt-5 border-t pt-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-ink-400">Ad set &amp; creative (Meta)</div>
                <div class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                    @if (!empty($adset))
                        <span class="text-ink-500">Age</span><span>{{ $adset['age_min'] ?? 18 }}–{{ $adset['age_max'] ?? 65 }}</span>
                        <span class="text-ink-500">Gender</span><span>{{ empty($adset['genders']) ? 'All' : implode(', ', array_map(fn($g)=> $g==1?'Male':'Female', $adset['genders'])) }}</span>
                        <span class="text-ink-500">Countries</span><span>{{ implode(', ', $adset['countries'] ?? []) }}</span>
                        @if (!empty($adset['interests']))<span class="text-ink-500">Interests</span><span>{{ implode(', ', $adset['interests']) }}</span>@endif
                        <span class="text-ink-500">Optimization</span><span>{{ $adset['optimization_goal'] ?? 'LINK_CLICKS' }}</span>
                    @endif
                    @if (!empty($creative))
                        <span class="text-ink-500">Page ID</span><span>{{ $creative['page_id'] ?? '—' }}</span>
                        @if (!empty($creative['headline']))<span class="text-ink-500">Headline</span><span>{{ $creative['headline'] }}</span>@endif
                        <span class="text-ink-500">Type</span><span class="capitalize">{{ $creative['type'] ?? 'image' }}</span>
                        <span class="text-ink-500">Media</span><span>{{ !empty($creative['video_path']) ? 'Video uploaded' : (!empty($creative['image_path']) ? 'Image uploaded' : (!empty($creative['image_url']) ? 'Image from URL' : '—')) }}</span>
                    @endif
                </div>
                @if (!empty($creative['message']))<p class="mt-2 rounded bg-ink-50 px-2 py-1 text-xs">{{ $creative['message'] }}</p>@endif

                @if (!empty($metaIds))
                    <div class="mt-3 text-[11px] text-ink-400">
                        <span class="font-semibold">Created on Meta:</span>
                        @foreach (['campaign_id'=>'Campaign','adset_id'=>'Ad set','video_id'=>'Video','creative_id'=>'Creative','ad_id'=>'Ad'] as $k=>$lbl)
                            @if (!empty($metaIds[$k]))<span class="mr-2">{{ $lbl }} <code>{{ $metaIds[$k] }}</code></span>@endif
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>

    <div class="admin-card p-5">
        <h2 class="font-semibold">Change log</h2>
        @forelse ($campaign->changes->sortByDesc('created_at') as $ch)
            <div class="mt-3 border-l-2 border-ink-100 pl-3 text-xs">
                <div class="font-medium capitalize">{{ $ch->field }}: {{ $ch->old_value ?? '—' }} → {{ $ch->new_value ?? '—' }}</div>
                <div class="text-ink-400">{{ $ch->source }} · {{ optional($ch->changedBy)->name ?? 'system' }} · {{ $ch->created_at->diffForHumans() }}</div>
            </div>
        @empty
            <p class="mt-3 text-sm text-ink-400">No changes yet.</p>
        @endforelse
    </div>
</div>
@endsection

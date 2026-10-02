@extends('layouts.admin')
@section('pageTitle', 'Ad Integrations')

@php
    $ok = fn ($v) => $v
        ? '<span class="status-pill bg-emerald-100 text-emerald-700">Set</span>'
        : '<span class="status-pill bg-rose-100 text-rose-700">Missing</span>';
@endphp

@section('content')
<div>
    <h1 class="font-display text-xl font-bold">Ad Integrations</h1>
    <p class="text-xs text-ink-500">Capture leads automatically from Meta Lead Ads and Google Ads lead forms. Credentials live in your <code>.env</code>.</p>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">

    {{-- Meta --}}
    <div class="admin-card p-5">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-bold">Meta Lead Ads</h2>
            @if ($status['meta']['enabled'])<span class="status-pill bg-emerald-100 text-emerald-700">Enabled</span>@else<span class="status-pill bg-amber-100 text-amber-700">Disabled</span>@endif
        </div>

        <label class="label">Webhook Callback URL</label>
        <div class="mb-3 flex gap-2">
            <input type="text" readonly value="{{ $status['meta']['webhook_url'] }}" class="input flex-1 font-mono text-xs" onclick="this.select()">
            <button type="button" class="btn-ghost btn-xs text-brand-700" onclick="navigator.clipboard.writeText('{{ $status['meta']['webhook_url'] }}');this.textContent='✓';setTimeout(()=>this.textContent='Copy',1200)">Copy</button>
        </div>

        <label class="label">Verify Token</label>
        <div class="mb-3 flex gap-2">
            <input type="text" readonly value="{{ $status['meta']['verify_token'] }}" placeholder="Set META_LEADS_VERIFY_TOKEN in .env" class="input flex-1 font-mono text-xs" onclick="this.select()">
        </div>

        <div class="flex items-center justify-between border-t py-2 text-sm"><span>App secret (signature)</span>{!! $ok($status['meta']['app_secret']) !!}</div>
        <div class="flex items-center justify-between border-t py-2 text-sm"><span>Page access token</span><span class="font-mono text-xs">{{ $status['meta']['page_access_token'] ?? '—' }}</span></div>
        <div class="flex items-center justify-between border-t py-2 text-sm"><span>Leads ingested</span><strong>{{ $status['meta']['ingested'] }}</strong></div>

        <p class="mt-2 text-[11px] text-ink-400">In Meta → Webhooks, subscribe the Page's <code>leadgen</code> field to this URL with the verify token above.</p>
    </div>

    {{-- Google --}}
    <div class="admin-card p-5">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-bold">Google Ads Lead Forms</h2>
            @if ($status['google']['enabled'])<span class="status-pill bg-emerald-100 text-emerald-700">Enabled</span>@else<span class="status-pill bg-amber-100 text-amber-700">Disabled</span>@endif
        </div>

        <label class="label">Webhook URL</label>
        <div class="mb-3 flex gap-2">
            <input type="text" readonly value="{{ $status['google']['webhook_url'] }}" class="input flex-1 font-mono text-xs" onclick="this.select()">
            <button type="button" class="btn-ghost btn-xs text-brand-700" onclick="navigator.clipboard.writeText('{{ $status['google']['webhook_url'] }}');this.textContent='✓';setTimeout(()=>this.textContent='Copy',1200)">Copy</button>
        </div>

        <div class="flex items-center justify-between border-t py-2 text-sm"><span>Webhook key</span><span class="font-mono text-xs">{{ $status['google']['key'] ?? '—' }}</span></div>
        <div class="flex items-center justify-between border-t py-2 text-sm"><span>Leads ingested</span><strong>{{ $status['google']['ingested'] }}</strong></div>

        <p class="mt-2 text-[11px] text-ink-400">On the Google lead form → Webhook integration, set this URL and the same key as <code>GOOGLE_LEADS_KEY</code>.</p>
    </div>
</div>

{{-- Recent ad leads --}}
<div class="admin-card mt-4 overflow-x-auto p-5">
    <h2 class="mb-3 text-sm font-bold">Recent Ad Leads</h2>
    <table class="admin-table">
        <thead>
            <tr><th>Lead</th><th>Name</th><th>Source</th><th>Campaign</th><th>Received</th></tr>
        </thead>
        <tbody>
            @forelse ($recentLeads as $lead)
                <tr>
                    <td><a href="{{ route('admin.crm.show', $lead) }}" class="font-mono text-brand-600 hover:underline">{{ $lead->lead_number ?? ('#' . $lead->id) }}</a></td>
                    <td class="text-sm">{{ $lead->name }}</td>
                    <td class="text-xs">{{ $lead->leadSource?->name ?? '—' }}</td>
                    <td class="font-mono text-xs">{{ $lead->external_campaign_id ?? '—' }}</td>
                    <td class="text-xs text-ink-500">{{ $lead->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-ink-500">No ad leads captured yet. They'll appear here once your webhooks are connected.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

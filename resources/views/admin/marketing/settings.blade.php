@extends('layouts.admin')
@section('pageTitle', 'Ad Provider Credentials')

@php
    $mask = fn ($v) => $v ? str_repeat('•', 8) . substr($v, -4) : '';
@endphp

@section('content')
<div class="flex items-center justify-between">
    <div>
        <h1 class="font-display text-xl font-bold">Ad Provider Credentials</h1>
        <p class="text-xs text-ink-500">Stored encrypted in the database — no <code>.env</code> needed. Secrets are never shown back in full; leave a secret blank to keep the saved value.</p>
    </div>
    <a href="{{ route('admin.marketing.accounts') }}" class="btn-ghost btn-sm">← Ad Accounts</a>
</div>

@if ($errors->any())<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">

    {{-- ───────────────── Google Ads ───────────────── --}}
    @php $g = $providers['google_ads']; $gc = $g['creds']; @endphp
    <form action="{{ route('admin.marketing.settings.update', 'google_ads') }}" method="POST" class="admin-card space-y-3 p-5">
        @csrf
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-bold">Google Ads</h2>
            @if ($g['configured'])
                <span class="status-pill bg-emerald-100 text-emerald-700">Configured</span>
            @else
                <span class="status-pill bg-amber-100 text-amber-700">Incomplete</span>
            @endif
        </div>

        <div>
            <label class="label">Client ID</label>
            <input type="text" name="client_id" value="{{ old('client_id', $gc['client_id'] ?? '') }}" class="input" placeholder="xxxx.apps.googleusercontent.com">
        </div>
        <div>
            <label class="label">Client Secret @if(!empty($gc['client_secret']))<span class="text-[10px] text-emerald-600">(saved {{ $mask($gc['client_secret']) }})</span>@endif</label>
            <input type="password" name="client_secret" value="" class="input" placeholder="{{ !empty($gc['client_secret']) ? 'leave blank to keep' : 'enter secret' }}" autocomplete="new-password">
        </div>
        <div>
            <label class="label">Developer Token @if(!empty($gc['developer_token']))<span class="text-[10px] text-emerald-600">(saved {{ $mask($gc['developer_token']) }})</span>@endif</label>
            <input type="password" name="developer_token" value="" class="input" placeholder="{{ !empty($gc['developer_token']) ? 'leave blank to keep' : 'enter token' }}" autocomplete="new-password">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="label">Login Customer ID (MCC)</label>
                <input type="text" name="login_customer_id" value="{{ old('login_customer_id', $gc['login_customer_id'] ?? '') }}" class="input" placeholder="1234567890">
            </div>
            <div>
                <label class="label">API Version</label>
                <input type="text" name="api_version" value="{{ old('api_version', $gc['api_version'] ?? 'v17') }}" class="input">
            </div>
        </div>
        <div>
            <label class="label">Redirect URI</label>
            <input type="url" name="redirect_uri" value="{{ old('redirect_uri', $gc['redirect_uri'] ?? $g['default_redirect']) }}" class="input">
            <p class="mt-1 text-[11px] text-ink-400">Register this exact URI in Google Cloud Console → Credentials.</p>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="enabled" value="0">
            <input type="checkbox" name="enabled" value="1" @checked($g['setting']->enabled ?? false) class="rounded border-ink-300">
            Enabled (allow connecting)
        </label>
        <button class="btn-primary btn-sm w-full">Save Google Ads credentials</button>
    </form>

    {{-- ───────────────── Meta Ads ───────────────── --}}
    @php $m = $providers['meta_ads']; $mcr = $m['creds']; @endphp
    <form action="{{ route('admin.marketing.settings.update', 'meta_ads') }}" method="POST" class="admin-card space-y-3 p-5">
        @csrf
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-bold">Meta Ads</h2>
            @if ($m['configured'])
                <span class="status-pill bg-emerald-100 text-emerald-700">Configured</span>
            @else
                <span class="status-pill bg-amber-100 text-amber-700">Incomplete</span>
            @endif
        </div>

        <div>
            <label class="label">App ID</label>
            <input type="text" name="app_id" value="{{ old('app_id', $mcr['app_id'] ?? '') }}" class="input" placeholder="1234567890123456">
        </div>
        <div>
            <label class="label">App Secret @if(!empty($mcr['app_secret']))<span class="text-[10px] text-emerald-600">(saved {{ $mask($mcr['app_secret']) }})</span>@endif</label>
            <input type="password" name="app_secret" value="" class="input" placeholder="{{ !empty($mcr['app_secret']) ? 'leave blank to keep' : 'enter secret' }}" autocomplete="new-password">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="label">API Version</label>
                <input type="text" name="api_version" value="{{ old('api_version', $mcr['api_version'] ?? 'v21.0') }}" class="input">
            </div>
            <div>
                <label class="label">Webhook Verify Token</label>
                <input type="text" name="webhook_verify_token" value="{{ old('webhook_verify_token', $mcr['webhook_verify_token'] ?? '') }}" class="input" placeholder="random string">
            </div>
        </div>
        <div>
            <label class="label">Redirect URI</label>
            <input type="url" name="redirect_uri" value="{{ old('redirect_uri', $mcr['redirect_uri'] ?? $m['default_redirect']) }}" class="input">
            <p class="mt-1 text-[11px] text-ink-400">Must be <strong>HTTPS</strong>. Add it to Facebook Login → Valid OAuth Redirect URIs.</p>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="enabled" value="0">
            <input type="checkbox" name="enabled" value="1" @checked($m['setting']->enabled ?? false) class="rounded border-ink-300">
            Enabled (allow connecting)
        </label>
        <button class="btn-primary btn-sm w-full">Save Meta Ads credentials</button>
    </form>
</div>

<div class="admin-card mt-4 p-5 text-sm text-ink-600">
    <h3 class="font-semibold">How connection works</h3>
    <ol class="mt-2 list-decimal space-y-1 pl-5 text-xs">
        <li>Enter the app credentials above, tick <em>Enabled</em>, and save. They're encrypted in <code>marketing_provider_settings</code>.</li>
        <li>Go to <a href="{{ route('admin.marketing.accounts') }}" class="text-brand-600 underline">Ad Accounts</a> — the provider now shows <strong>Configured</strong> and the Connect button is active.</li>
        <li>Click <strong>Connect</strong> → authorize on Google/Meta → you're redirected back and an encrypted token is stored.</li>
        <li>Use <strong>Check</strong> to verify a connection is live, and <strong>Sync accounts</strong> to import ad accounts.</li>
    </ol>
</div>
@endsection

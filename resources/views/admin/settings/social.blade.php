@extends('layouts.admin')
@section('pageTitle', 'Social Login')

@section('content')
<div class="flex items-center justify-between">
    <div>
        <h1 class="font-display text-xl font-bold">Social Login</h1>
        <p class="text-xs text-ink-500">Sign-in buttons on the customer website. Settings take effect immediately; no <code>.env</code> edits needed.</p>
    </div>
</div>

@if (session('success'))<div class="mt-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('success') }}</div>@endif
@if (session('error'))<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ session('error') }}</div>@endif
@if ($errors->any())<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

@unless ($status['stored_in_db'])
    <div class="mt-3 text-[11px] text-amber-600">Using <code>.env</code> fallback — nothing saved in the database yet.</div>
@endunless

<form action="{{ route('admin.social-login-settings.update') }}" method="POST" class="mt-4">
    @csrf
    @method('PUT')

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

        {{-- Google --}}
        <div class="admin-card p-5">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wide text-ink-500">Google</h2>
                <span class="text-[11px] font-semibold {{ $status['google']['enabled'] ? 'text-emerald-600' : 'text-ink-400' }}">
                    {{ $status['google']['enabled'] ? 'Live' : 'Off' }}
                </span>
            </div>

            <label class="mt-3 flex items-center gap-2 text-sm">
                <input type="hidden" name="google_enabled" value="0">
                <input type="checkbox" name="google_enabled" value="1" @checked($status['google']['enabled']) class="rounded border-slate-300">
                Enable "Continue with Google"
            </label>
            <p class="mt-1 text-[11px] text-ink-400">Shows only when enabled <em>and</em> a web client ID is set.</p>

            <label class="label mt-4">Web client ID</label>
            <input type="text" name="google_web_client_id" value="{{ old('google_web_client_id', $status['google']['web_client_id']) }}"
                   class="input font-mono text-xs" placeholder="1234567890-abcxyz.apps.googleusercontent.com" autocomplete="off">
            <p class="mt-1 text-[11px] text-ink-400">
                Google Cloud Console → Credentials → OAuth 2.0 Client ID (type <strong>Web application</strong>).
                Add <code>{{ config('app.url') }}</code> under "Authorized JavaScript origins". No client secret is needed — it's a public value.
            </p>
        </div>

        {{-- Facebook --}}
        <div class="admin-card p-5">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wide text-ink-500">Facebook</h2>
                <span class="text-[11px] font-semibold {{ $status['facebook']['enabled'] ? 'text-emerald-600' : 'text-ink-400' }}">
                    {{ $status['facebook']['enabled'] ? 'Live' : 'Off' }}
                </span>
            </div>

            <label class="mt-3 flex items-center gap-2 text-sm">
                <input type="hidden" name="facebook_enabled" value="0">
                <input type="checkbox" name="facebook_enabled" value="1" @checked($status['facebook']['enabled']) class="rounded border-slate-300">
                Enable "Continue with Facebook"
            </label>
            <p class="mt-1 text-[11px] text-ink-400">Shows only when enabled <em>and</em> both app ID and app secret are set.</p>

            <label class="label mt-4">App ID</label>
            <input type="text" name="facebook_app_id" value="{{ old('facebook_app_id', $status['facebook']['app_id']) }}"
                   class="input font-mono text-xs" placeholder="1234567890123456" autocomplete="off">

            <label class="label mt-3">App secret</label>
            <input type="password" name="facebook_app_secret"
                   class="input font-mono text-xs" placeholder="{{ $status['facebook']['secret_set'] ? $status['facebook']['secret_masked'] : 'not set' }}" autocomplete="off">
            <p class="mt-1 text-[11px] text-ink-400">
                Meta for Developers → your app → Settings → Basic. The secret is stored encrypted and never shown again — leave blank to keep the current one.
            </p>
        </div>

    </div>

    <div class="mt-4 flex justify-end">
        <button class="btn-primary">Save settings</button>
    </div>
</form>
@endsection

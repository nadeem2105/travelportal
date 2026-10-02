@extends('layouts.admin')
@section('pageTitle', 'SMS & OTP')

@php
    $labels = ['fast2sms' => 'Fast2SMS', 'msg91' => 'MSG91', 'twilio' => 'Twilio'];
@endphp

@section('content')
<div class="flex items-center justify-between">
    <div>
        <h1 class="font-display text-xl font-bold">SMS &amp; Phone OTP</h1>
        <p class="text-xs text-ink-500">Transactional SMS and phone-OTP for the mobile app. Credentials are stored encrypted in the database — no <code>.env</code> edits needed.</p>
    </div>
</div>

@if (session('success'))<div class="mt-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('success') }}</div>@endif
@if (session('error'))<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ session('error') }}</div>@endif
@if ($errors->any())<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

@if ($status['last_tested_at'])
    <div class="mt-3 text-[11px] text-ink-400">
        Last test: <span class="font-semibold {{ $status['last_test_status'] === 'ok' ? 'text-emerald-600' : 'text-rose-600' }}">{{ strtoupper($status['last_test_status'] ?? '—') }}</span>
        · {{ $status['last_test_message'] }} · {{ $status['last_tested_at']->diffForHumans() }}
    </div>
@endif

<form action="{{ route('admin.sms-settings.update') }}" method="POST" class="mt-4">
    @csrf
    @method('PUT')

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

        {{-- Master + defaults --}}
        <div class="admin-card p-5">
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-ink-500">General</h2>

            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" @checked($status['enabled']) class="rounded border-slate-300">
                Enable SMS sending
            </label>
            <p class="mt-1 text-[11px] text-ink-400">When off, OTP/SMS dispatch is skipped everywhere.</p>

            <label class="label mt-4">Active provider</label>
            <select name="default" class="input">
                @foreach ($labels as $val => $label)
                    <option value="{{ $val }}" @selected(($status['default'] ?? '') === $val)>{{ $label }}</option>
                @endforeach
            </select>

            <h3 class="mt-5 mb-2 text-xs font-bold uppercase tracking-wide text-ink-400">OTP behaviour</h3>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">Code length</label>
                    <input type="number" name="otp_length" min="4" max="8" value="{{ old('otp_length', $status['otp']['length'] ?? 6) }}" class="input">
                </div>
                <div>
                    <label class="label">Valid for (min)</label>
                    <input type="number" name="otp_ttl_minutes" min="1" max="60" value="{{ old('otp_ttl_minutes', $status['otp']['ttl_minutes'] ?? 10) }}" class="input">
                </div>
                <div>
                    <label class="label">Max attempts</label>
                    <input type="number" name="otp_max_attempts" min="1" max="10" value="{{ old('otp_max_attempts', $status['otp']['max_attempts'] ?? 5) }}" class="input">
                </div>
                <div>
                    <label class="label">Resend cooldown (s)</label>
                    <input type="number" name="otp_resend_cooldown_seconds" min="15" max="600" value="{{ old('otp_resend_cooldown_seconds', $status['otp']['resend_cooldown_seconds'] ?? 60) }}" class="input">
                </div>
            </div>
            <label class="label mt-3">OTP message template</label>
            <textarea name="otp_message" rows="3" class="input text-xs" placeholder="Your code is {code}. Valid for {ttl} minutes.">{{ old('otp_message', $status['otp']['message'] ?? '') }}</textarea>
            <p class="mt-1 text-[11px] text-ink-400"><code>{code}</code> and <code>{ttl}</code> are replaced at send time. Align wording with your approved DLT/OTP template.</p>
        </div>

        {{-- PLACEHOLDER_PROVIDERS --}}
        <div class="admin-card p-5">
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-ink-500">Provider credentials</h2>

            @php
                $secretPlaceholders = ['api_key' => 'API key', 'auth_key' => 'Auth key', 'auth_token' => 'Auth token'];
                $scalarLabels = [
                    'route' => 'Route (dlt / otp / q)', 'sender_id' => 'Sender ID', 'message_id' => 'DLT message/template ID',
                    'template_id' => 'Template ID', 'default_country' => 'Default country code',
                    'sid' => 'Account SID', 'from' => 'From number (E.164)', 'messaging_service_sid' => 'Messaging Service SID',
                ];
            @endphp

            @foreach ($labels as $name => $label)
                <div class="mb-4 rounded-lg border border-slate-100 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-semibold text-ink-700">{{ $label }}</span>
                        @if (($status['default'] ?? '') === $name)
                            <span class="status-pill bg-brand-100 text-brand-700">Active</span>
                        @endif
                    </div>

                    @foreach ($providers[$name]['secrets'] as $sk => $meta)
                        <div class="mb-2">
                            <div class="mb-1 flex items-center justify-between">
                                <label class="label mb-0">{{ $secretPlaceholders[$sk] ?? $sk }}</label>
                                @if ($meta['set'])
                                    <span class="status-pill bg-emerald-100 text-emerald-700">Set · {{ $meta['masked'] }}</span>
                                @else
                                    <span class="status-pill bg-ink-100 text-ink-500">Not set</span>
                                @endif
                            </div>
                            <input name="providers[{{ $name }}][{{ $sk }}]" value="" class="input font-mono text-xs" placeholder="{{ $meta['set'] ? 'Leave blank to keep current' : ($secretPlaceholders[$sk] ?? $sk) }}">
                        </div>
                    @endforeach

                    @foreach ($providers[$name]['scalars'] as $k => $val)
                        <div class="mb-2">
                            <label class="label">{{ $scalarLabels[$k] ?? $k }}</label>
                            <input name="providers[{{ $name }}][{{ $k }}]" value="{{ old('providers.' . $name . '.' . $k, $val) }}" class="input font-mono text-xs">
                        </div>
                    @endforeach
                </div>
            @endforeach

            <p class="text-[11px] text-ink-400">Secrets are never shown in full — leave blank to keep the stored value. Non-secret fields clear to the <code>.env</code> fallback when emptied.</p>
        </div>

    </div>

    <div class="mt-4 flex justify-end">
        <button class="btn-primary">Save SMS settings</button>
    </div>
</form>

{{-- PLACEHOLDER_TEST --}}
<div class="admin-card mt-4 p-5">
    <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-ink-500">Send a test SMS</h2>
    <form action="{{ route('admin.sms-settings.test') }}" method="POST" class="flex flex-wrap items-end gap-3">
        @csrf
        <div>
            <label class="label">Phone number</label>
            <input name="test_phone" value="{{ old('test_phone') }}" class="input" placeholder="e.g. 9876543210 or +919876543210" required>
        </div>
        <button class="btn-primary btn-sm">Send test</button>
        <p class="text-[11px] text-ink-400">Uses the currently active provider. Save your changes first.</p>
    </form>
</div>
@endsection

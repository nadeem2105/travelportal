@extends('layouts.admin')
@section('pageTitle', 'WhatsApp Settings')

@php
    $tpl = fn ($k) => $templates[$k] ?? '';
    $att = fn ($k) => (bool) ($attach[$k] ?? false);
@endphp

@section('content')
<div class="flex items-center justify-between">
    <div>
        <h1 class="font-display text-xl font-bold">WhatsApp Settings</h1>
        <p class="text-xs text-ink-500">Cloud API credentials, templates &amp; the AI assistant. Saved (encrypted) in the database — no <code>.env</code> edits needed.</p>
    </div>
    <form action="{{ route('admin.whatsapp-settings.test') }}" method="POST">
        @csrf
        <button class="btn-primary btn-sm">Test Connection</button>
    </form>
</div>

@if (session('success'))<div class="mt-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('success') }}</div>@endif
@if (session('error'))<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ session('error') }}</div>@endif
@if ($errors->any())<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

@unless ($storedInDb)
    <div class="mt-3 rounded-lg bg-amber-50 px-4 py-2 text-xs text-amber-700">
        Currently reading from <code>.env</code>. Saving this form moves control to the database (values below override <code>.env</code>; blank secrets keep whatever <code>.env</code> provides).
    </div>
@endunless

<form action="{{ route('admin.whatsapp-settings.update') }}" method="POST" class="mt-4">
    @csrf
    @method('PUT')

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

        {{-- Credentials --}}
        <div class="admin-card p-5">
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-ink-500">Cloud API credentials</h2>

            <label class="mb-3 flex items-center gap-2 text-sm">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" @checked($status['enabled'])>
                <span>Sending enabled (master switch)</span>
            </label>

            <label class="label">Phone number ID</label>
            <input name="phone_number_id" value="{{ old('phone_number_id', $status['phone_number_id']) }}" class="input mb-3 font-mono text-xs" placeholder="from Meta → WhatsApp → API Setup">

            <label class="label">WhatsApp Business Account ID (WABA)</label>
            <input name="waba_id" value="{{ old('waba_id', $status['waba_id']) }}" class="input mb-3 font-mono text-xs">

            <label class="label">Access token</label>
            <input name="access_token" value="" class="input mb-1 font-mono text-xs" placeholder="{{ $status['has_access_token'] ? 'Stored: ' . $status['access_token'] . ' — leave blank to keep' : 'permanent System User token' }}">
            <p class="mb-3 text-[11px] text-ink-400">Leave blank to keep the current token.</p>

            <label class="label">App secret (webhook signature)</label>
            <input name="app_secret" value="" class="input mb-1 font-mono text-xs" placeholder="{{ $status['app_secret_set'] ? 'Set — leave blank to keep' : 'Meta App → Settings → Basic' }}">
            <p class="mb-3 text-[11px] text-ink-400">Leave blank to keep the current secret.</p>

            <label class="label">App ID</label>
            <input name="app_id" value="{{ old('app_id', $status['app_id']) }}" class="input mb-3 font-mono text-xs">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">API version</label>
                    <input name="api_version" value="{{ old('api_version', $status['api_version']) }}" class="input font-mono text-xs" placeholder="v21.0">
                </div>
                <div>
                    <label class="label">Default template language</label>
                    <input name="default_template_lang" value="{{ old('default_template_lang', $status['default_template_lang']) }}" class="input font-mono text-xs" placeholder="en_US">
                </div>
            </div>
        </div>

        {{-- Webhook --}}
        <div class="admin-card p-5">
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-ink-500">Webhook (Meta configuration)</h2>
            <p class="mb-3 text-xs text-ink-500">In Meta → your app → WhatsApp → Configuration, set the callback URL and verify token below, and subscribe to the <code>messages</code> field.</p>

            <label class="label">Callback URL</label>
            <div class="mb-3 flex gap-2">
                <input type="text" readonly value="{{ $webhookUrl }}" class="input flex-1 font-mono text-xs" onclick="this.select()">
                <button type="button" class="btn-ghost btn-xs text-brand-700"
                        onclick="navigator.clipboard.writeText('{{ $webhookUrl }}');this.textContent='✓';setTimeout(()=>this.textContent='Copy',1200)">Copy</button>
            </div>

            <label class="label">Verify token</label>
            <input name="verify_token" value="{{ old('verify_token', $status['verify_token']) }}" class="input mb-3 font-mono text-xs" placeholder="any random string you choose">

            <div class="rounded-lg bg-ink-50 p-3 text-xs text-ink-500">
                Subscribe to field: <span class="font-mono">messages</span>. The webhook is authenticated by the app-secret signature; requests failing the signature are rejected.
            </div>
        </div>

        {{-- Transactional templates --}}
        <div class="admin-card p-5">
            <h2 class="mb-1 text-sm font-bold uppercase tracking-wide text-ink-500">Transactional templates</h2>
            <p class="mb-3 text-xs text-ink-500">Approved template names (Meta Business Manager). Blank = skip WhatsApp for that event; email/SMS still fire.</p>

            <label class="label">Booking confirmed</label>
            <input name="templates[booking_confirmed]" value="{{ old('templates.booking_confirmed', $tpl('booking_confirmed')) }}" class="input mb-3 font-mono text-xs" placeholder="booking_confirmed">

            <label class="label">Booking invoice</label>
            <input name="templates[booking_invoice]" value="{{ old('templates.booking_invoice', $tpl('booking_invoice')) }}" class="input mb-3 font-mono text-xs" placeholder="booking_invoice">

            <label class="label">Booking cancelled</label>
            <input name="templates[booking_cancelled]" value="{{ old('templates.booking_cancelled', $tpl('booking_cancelled')) }}" class="input mb-3 font-mono text-xs" placeholder="booking_cancelled">

            <label class="label">Hotel voucher</label>
            <input name="templates[hotel_voucher]" value="{{ old('templates.hotel_voucher', $tpl('hotel_voucher')) }}" class="input mb-3 font-mono text-xs" placeholder="hotel_voucher">

            <label class="label">Cab voucher</label>
            <input name="templates[cab_voucher]" value="{{ old('templates.cab_voucher', $tpl('cab_voucher')) }}" class="input mb-3 font-mono text-xs" placeholder="cab_voucher">

            <label class="label">Quotation sent</label>
            <input name="templates[quotation_sent]" value="{{ old('templates.quotation_sent', $tpl('quotation_sent')) }}" class="input font-mono text-xs" placeholder="quotation_sent">

            <h3 class="mt-4 mb-2 text-xs font-bold uppercase tracking-wide text-ink-400">Trip operations</h3>
            <p class="mb-2 text-[11px] text-ink-400">Proactive templates used by the Trip Operations module (driver + customer). Blank = skip WhatsApp for that event.</p>

            <label class="label">Customer itinerary</label>
            <input name="templates[trip_customer_itinerary]" value="{{ old('templates.trip_customer_itinerary', $tpl('trip_customer_itinerary')) }}" class="input mb-3 font-mono text-xs" placeholder="trip_customer_itinerary">

            <label class="label">Driver assigned</label>
            <input name="templates[trip_driver_assigned]" value="{{ old('templates.trip_driver_assigned', $tpl('trip_driver_assigned')) }}" class="input mb-3 font-mono text-xs" placeholder="trip_driver_assigned">

            <label class="label">Driver sheet</label>
            <input name="templates[trip_driver_sheet]" value="{{ old('templates.trip_driver_sheet', $tpl('trip_driver_sheet')) }}" class="input mb-3 font-mono text-xs" placeholder="trip_driver_sheet">

            <label class="label">Driver reminder</label>
            <input name="templates[trip_driver_reminder]" value="{{ old('templates.trip_driver_reminder', $tpl('trip_driver_reminder')) }}" class="input mb-3 font-mono text-xs" placeholder="trip_driver_reminder">

            <label class="label">Tomorrow's plan</label>
            <input name="templates[trip_tomorrow_plan]" value="{{ old('templates.trip_tomorrow_plan', $tpl('trip_tomorrow_plan')) }}" class="input font-mono text-xs" placeholder="trip_tomorrow_plan">

            <h3 class="mt-4 mb-2 text-xs font-bold uppercase tracking-wide text-ink-400">Attach PDF to template</h3>
            <p class="mb-2 text-[11px] text-ink-400">Only enable when the matching template has a <em>document header</em> in Meta, or the send is rejected.</p>
            <div class="space-y-1.5 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" name="attach[quotation_sent]" value="1" @checked($att('quotation_sent'))> Quotation PDF → quotation_sent</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="attach[booking_confirmed]" value="1" @checked($att('booking_confirmed'))> Invoice → booking_confirmed</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="attach[booking_itinerary]" value="1" @checked($att('booking_itinerary'))> Itinerary PDF</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="attach[booking_invoice]" value="1" @checked($att('booking_invoice'))> Invoice PDF</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="attach[hotel_voucher]" value="1" @checked($att('hotel_voucher'))> Hotel voucher PDF → hotel_voucher</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="attach[cab_voucher]" value="1" @checked($att('cab_voucher'))> Cab voucher PDF → cab_voucher</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="attach[trip_customer_itinerary]" value="1" @checked($att('trip_customer_itinerary'))> Itinerary PDF → trip_customer_itinerary</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="attach[trip_driver_sheet]" value="1" @checked($att('trip_driver_sheet'))> Driver sheet PDF → trip_driver_sheet</label>
            </div>
        </div>

        {{-- AI assistant --}}
        <div class="admin-card p-5">
            <h2 class="mb-1 text-sm font-bold uppercase tracking-wide text-ink-500">AI Travel Assistant</h2>
            <p class="mb-3 text-xs text-ink-500">LLM self-service over inbound WhatsApp. Keyword auto-replies always run first; this handles the rest. Needs an AI provider key (set on the <a href="{{ route('admin.ai-settings.index') }}" class="text-brand-700 underline">AI Providers</a> page).</p>

            <label class="mb-3 flex items-center gap-2 text-sm">
                <input type="hidden" name="assistant_enabled" value="0">
                <input type="checkbox" name="assistant_enabled" value="1" @checked((bool) ($assistant['enabled'] ?? false))>
                <span>Enable AI assistant</span>
            </label>

            <label class="mb-3 flex items-center gap-2 text-sm">
                <input type="hidden" name="assistant_require_verification" value="0">
                <input type="checkbox" name="assistant_require_verification" value="1" @checked((bool) ($assistant['require_verification'] ?? true))>
                <span>Require identity verification before private data <span class="text-rose-600">(recommended)</span></span>
            </label>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">Provider override</label>
                    @php $curProvider = old('assistant_provider', $assistant['provider'] ?? ''); @endphp
                    <select name="assistant_provider" class="input text-xs">
                        <option value="" @selected($curProvider === '')>Use shared AI default</option>
                        @foreach (['openai' => 'OpenAI', 'anthropic' => 'Anthropic (Claude)', 'gemini' => 'Google Gemini', 'groq' => 'Groq', 'openrouter' => 'OpenRouter'] as $val => $lbl)
                            <option value="{{ $val }}" @selected($curProvider === $val)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-ink-400">Pick a provider — not a model. Leave on "shared AI default" to use the AI Providers default.</p>
                </div>
                <div>
                    <label class="label">Model override</label>
                    <input name="assistant_model" value="{{ old('assistant_model', $assistant['model'] ?? '') }}" class="input font-mono text-xs" placeholder="Blank = provider's own model">
                    <p class="mt-1 text-[11px] text-ink-400">Optional. Blank uses the model set on the AI Providers page. A model name typed here must match the chosen provider.</p>
                </div>
                <div>
                    <label class="label">Max tool iterations</label>
                    <input type="number" name="assistant_max_tool_iterations" value="{{ old('assistant_max_tool_iterations', $assistant['max_tool_iterations'] ?? 5) }}" min="1" max="15" class="input font-mono text-xs">
                </div>
                <div>
                    <label class="label">History limit</label>
                    <input type="number" name="assistant_history_limit" value="{{ old('assistant_history_limit', $assistant['history_limit'] ?? 12) }}" min="1" max="50" class="input font-mono text-xs">
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 flex justify-end">
        <button class="btn-primary">Save WhatsApp settings</button>
    </div>
</form>
@endsection

@extends('layouts.admin')
@section('pageTitle', 'AI Providers')

@php
    $labels = ['openai' => 'OpenAI', 'anthropic' => 'Anthropic (Claude)', 'gemini' => 'Google Gemini', 'groq' => 'Groq', 'openrouter' => 'OpenRouter'];
    $placeholders = ['openai' => 'sk-...', 'anthropic' => 'sk-ant-...', 'gemini' => 'AIza...', 'groq' => 'gsk_...', 'openrouter' => 'sk-or-...'];
    $modelHints = ['openai' => 'gpt-4o', 'anthropic' => 'claude-3-5-sonnet-latest', 'gemini' => 'gemini-1.5-flash', 'groq' => 'llama-3.3-70b-versatile', 'openrouter' => 'openai/gpt-4o-mini'];
@endphp

@section('content')
<div class="flex items-center justify-between">
    <div>
        <h1 class="font-display text-xl font-bold">AI Providers</h1>
        <p class="text-xs text-ink-500">API keys for the marketing copilot and the WhatsApp AI assistant. Stored encrypted in the database — no <code>.env</code> edits needed.</p>
    </div>
    <form action="{{ route('admin.ai-settings.test') }}" method="POST">
        @csrf
        <button class="btn-primary btn-sm">Test Default Provider</button>
    </form>
</div>

@if (session('success'))<div class="mt-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ session('success') }}</div>@endif
@if (session('error'))<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ session('error') }}</div>@endif
@if ($errors->any())<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

<form action="{{ route('admin.ai-settings.update') }}" method="POST" class="mt-4">
    @csrf
    @method('PUT')

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

        {{-- Provider keys --}}
        <div class="admin-card p-5">
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-ink-500">Provider API keys</h2>

            @foreach (array_keys($labels) as $name)
                <div class="mb-4">
                    <div class="mb-1 flex items-center justify-between">
                        <label class="label mb-0">{{ $labels[$name] }}</label>
                        @if ($providers[$name]['set'])
                            <span class="status-pill bg-emerald-100 text-emerald-700">Set · {{ $providers[$name]['masked'] }}</span>
                        @else
                            <span class="status-pill bg-ink-100 text-ink-500">Not set</span>
                        @endif
                    </div>
                    <input name="keys[{{ $name }}]" value="" class="input mb-2 font-mono text-xs" placeholder="{{ $providers[$name]['set'] ? 'Leave blank to keep current key' : $placeholders[$name] }}">
                    <input name="models[{{ $name }}]" value="{{ old('models.' . $name, $providers[$name]['model']) }}" class="input font-mono text-xs" placeholder="Model for {{ $labels[$name] }} — e.g. {{ $modelHints[$name] }}">
                </div>
            @endforeach

            <p class="text-[11px] text-ink-400">Leave a key blank to keep the stored one — keys are never shown in full. Each provider uses its own model (falls back to the default model below if blank).</p>
        </div>

        {{-- Defaults --}}
        <div class="admin-card p-5">
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-ink-500">Defaults</h2>

            <label class="label">Default provider</label>
            <select name="default_provider" class="input mb-3">
                @foreach ($labels as $val => $label)
                    <option value="{{ $val }}" @selected($defaults['default_provider'] === $val)>{{ $label }}</option>
                @endforeach
            </select>

            <label class="label">Default model</label>
            <input name="default_model" value="{{ old('default_model', $defaults['default_model']) }}" class="input mb-3 font-mono text-xs" placeholder="e.g. gpt-4o-mini">
            <p class="text-[11px] text-ink-400">Use a tool-calling capable model (e.g. <span class="font-mono">gpt-4o</span>) if you enable the WhatsApp AI assistant.</p>

            <div class="mt-4 border-t border-slate-100 pt-4">
                <label class="label">Ad Creative Studio — image generation</label>
                <label class="mt-1 flex items-center gap-2 text-sm">
                    <input type="hidden" name="enable_image" value="0">
                    <input type="checkbox" name="enable_image" value="1" @checked($defaults['enable_image'] ?? false) class="rounded border-slate-300">
                    Enable AI image generation (uses the OpenAI key above)
                </label>
                <input name="image_model" value="{{ old('image_model', $defaults['image_model'] ?? 'gpt-image-1') }}" class="input mt-2 font-mono text-xs" placeholder="Image model — e.g. gpt-image-1">
                <p class="mt-1 text-[11px] text-ink-400">When off, the Creative Studio composites your portal images with the brand overlay (no AI image cost).</p>
            </div>

            <div class="mt-4 rounded-lg bg-ink-50 p-3 text-xs text-ink-500">
                The WhatsApp assistant's own enable switch and provider/model overrides live on the
                <a href="{{ route('admin.whatsapp-settings.index') }}" class="text-brand-700 underline">WhatsApp Settings</a> page.
            </div>
        </div>
    </div>

    <div class="mt-4 flex justify-end">
        <button class="btn-primary">Save AI settings</button>
    </div>
</form>
@endsection

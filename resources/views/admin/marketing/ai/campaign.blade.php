@extends('layouts.admin')
@section('pageTitle', 'AI Campaign Creator')

@php $b = $brief ?? []; @endphp

@section('content')
<a href="{{ route('admin.marketing.campaigns.index') }}" class="text-sm text-ink-500 hover:text-brand-700">← Campaigns</a>
<h1 class="mt-2 font-display text-xl font-bold">✨ AI Campaign Creator</h1>
<p class="text-xs text-ink-500">Give a short brief; AI drafts ad copy and targeting. You review, then save a draft — publishing still creates it <strong>paused</strong>, no spend.</p>

@if (session('error'))<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ session('error') }}</div>@endif

@unless ($aiConfigured ?? false)
    <div class="mt-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
        No AI provider is configured. Set <code>AI_DEFAULT_PROVIDER</code> (openai / anthropic / gemini) and the matching API key in your <code>.env</code>, then run <code>php artisan config:clear</code>. The brief form still works, but generation is disabled until then.
    </div>
@endunless

@if ($errors->any())<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
    {{-- Brief --}}
    @php
        $accountsJs = $accounts->map(fn ($a) => ['id' => $a->id, 'name' => $a->account_name, 'provider' => $a->provider])->values();
    @endphp
    <form action="{{ route('admin.marketing.ai.campaign.generate') }}" method="POST" class="admin-card space-y-3 p-5"
          x-data="{ provider: '{{ old('provider', $b['provider'] ?? 'meta_ads') }}', accountId: '{{ old('account_id', $b['account_id'] ?? '') }}', accounts: {{ \Illuminate\Support\Js::from($accountsJs) }} }">
        @csrf
        <h2 class="font-semibold">Brief</h2>
        <div>
            <label class="label">Platform *</label>
            <select name="provider" x-model="provider" class="input" required>
                <option value="meta_ads">Meta Ads</option>
                <option value="google_ads">Google Ads</option>
            </select>
        </div>
        <div>
            <label class="label">Ad Account</label>
            <select name="account_id" x-model="accountId" class="input">
                <option value="">— select account —</option>
                <template x-for="a in accounts.filter(x => x.provider === provider)" :key="a.id">
                    <option :value="a.id" x-text="a.name"></option>
                </template>
            </select>
            <p class="mt-1 text-[11px] text-ink-400" x-show="accounts.filter(x => x.provider === provider).length === 0">
                No <span x-text="provider === 'google_ads' ? 'Google' : 'Meta'"></span> accounts imported yet — connect and sync on the Ad Accounts page.
            </p>
        </div>
        <div>
            <label class="label">Objective</label>
            <select name="objective" class="input">
                @foreach ($objectives as $o)<option value="{{ $o }}" @selected(old('objective', $b['objective'] ?? '')===$o)>{{ $o }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="label">Destination</label>
            <input type="text" name="destination" value="{{ old('destination', $b['destination'] ?? '') }}" class="input" placeholder="Kashmir">
        </div>
        <div>
            <label class="label">Product / package</label>
            <input type="text" name="product" value="{{ old('product', $b['product'] ?? '') }}" class="input" placeholder="7-day Kashmir Honeymoon package">
        </div>
        <div>
            <label class="label">Landing page URL</label>
            <input type="url" name="landing_page" value="{{ old('landing_page', $b['landing_page'] ?? '') }}" class="input" placeholder="https://…">
        </div>
        <div>
            <label class="label">Target audience</label>
            <input type="text" name="audience" value="{{ old('audience', $b['audience'] ?? '') }}" class="input" placeholder="Couples 25–40, metro cities, honeymoon intent">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="label">Budget context</label>
                <input type="text" name="budget" value="{{ old('budget', $b['budget'] ?? '') }}" class="input" placeholder="₹1,000/day">
            </div>
            <div>
                <label class="label">Tone</label>
                <input type="text" name="tone" value="{{ old('tone', $b['tone'] ?? '') }}" class="input" placeholder="Warm, aspirational">
            </div>
        </div>
        <div>
            <label class="label">Extra notes</label>
            <textarea name="extra" rows="3" class="input" placeholder="Anything specific to include or avoid. Do not put made-up prices/dates here.">{{ old('extra', $b['extra'] ?? '') }}</textarea>
        </div>
        <p class="text-[11px] text-ink-400">AI will not invent prices, dates or discounts. Only facts you type here are used.</p>
        <button class="btn-primary btn-sm w-full" @disabled(! ($aiConfigured ?? false))>✨ Generate suggestions</button>
    </form>

    {{-- Results --}}
    <div class="admin-card p-5">
        <h2 class="font-semibold">Suggestions</h2>
        @if (empty($generated))
            <p class="mt-6 text-center text-sm text-ink-400">Fill the brief and generate to see AI drafts here.</p>
        @else
            @php
                $list = function ($items, $label) {
                    if (empty($items)) return '';
                    $h = '<div class="mt-3"><div class="text-xs font-semibold uppercase tracking-wide text-ink-400">'.$label.'</div><ul class="mt-1 space-y-1">';
                    foreach ($items as $i) $h .= '<li class="rounded bg-ink-50 px-2 py-1 text-sm">'.e($i).'</li>';
                    return $h.'</ul></div>';
                };
            @endphp

            <div class="mt-2 rounded-lg border border-brand-100 bg-brand-50/40 p-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-ink-400">Campaign name</div>
                <div class="mt-1 font-mono text-sm">{{ $generated['name'] }}</div>
            </div>

            {!! $list($generated['headlines'] ?? [], 'Headlines') !!}
            {!! $list($generated['primary_texts'] ?? [], 'Primary texts') !!}
            {!! $list($generated['descriptions'] ?? [], 'Descriptions') !!}

            @if (! empty($generated['target_audience']))
                <div class="mt-3"><div class="text-xs font-semibold uppercase tracking-wide text-ink-400">Target audience</div><p class="mt-1 text-sm">{{ $generated['target_audience'] }}</p></div>
            @endif

            {!! $list($generated['keywords'] ?? [], 'Keywords (Google)') !!}

            @if (! empty($generated['notes']))
                <div class="mt-3"><div class="text-xs font-semibold uppercase tracking-wide text-ink-400">Notes</div><p class="mt-1 text-sm text-ink-500">{{ $generated['notes'] }}</p></div>
            @endif

            {{-- Save as draft: reuses the standard campaign store; copy is stored in notes --}}
            <form action="{{ route('admin.marketing.campaigns.store') }}" method="POST" class="mt-5 space-y-3 border-t pt-4">
                @csrf
                <input type="hidden" name="name" value="{{ $generated['name'] }}">
                <input type="hidden" name="provider" value="{{ $b['provider'] ?? 'meta_ads' }}">
                <input type="hidden" name="objective" value="{{ $b['objective'] ?? 'Leads' }}">
                <input type="hidden" name="destination" value="{{ $b['destination'] ?? '' }}">
                <input type="hidden" name="landing_page" value="{{ $b['landing_page'] ?? '' }}">
                <input type="hidden" name="budget_type" value="daily">
                <input type="hidden" name="currency" value="INR">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Ad account</label>
                        <select name="account_id" class="input text-sm">
                            <option value="">— select —</option>
                            @foreach ($accounts as $acc)
                                @if ($acc->provider === ($b['provider'] ?? null))
                                    <option value="{{ $acc->id }}" @selected((string)($b['account_id'] ?? '') === (string)$acc->id)>{{ $acc->account_name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Daily budget (₹)</label>
                        <input type="number" step="0.01" min="0" name="daily_budget" class="input text-sm" placeholder="1000">
                    </div>
                </div>
                <p class="text-[11px] text-ink-400">Saves a draft with these fields. Refine copy, budget and targeting on the campaign page before publishing (it publishes paused).</p>
                <button class="btn-primary btn-sm w-full">Save as draft →</button>
            </form>
        @endif
    </div>
</div>
@endsection

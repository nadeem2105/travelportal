@extends('layouts.admin')
@section('pageTitle', 'Create Campaign')

@php $p = $prefill ?? []; @endphp

@section('content')
<a href="{{ route('admin.marketing.campaigns.index') }}" class="text-sm text-ink-500 hover:text-brand-700">← Campaigns</a>
<h1 class="mt-2 font-display text-xl font-bold">Create Campaign</h1>
<p class="text-xs text-ink-500">Builds a local draft. Publishing later creates it <strong>paused</strong> on the platform — no spend until you explicitly activate.</p>

@if ($errors->any())<div class="mt-3 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form action="{{ route('admin.marketing.campaigns.store') }}" method="POST" enctype="multipart/form-data" class="admin-card mt-4 max-w-3xl space-y-4 p-6"
      x-data="{ budgetType: '{{ old('budget_type', 'daily') }}', imgSource: '{{ old('img_source', 'upload') }}', creativeType: '{{ old('creative_type', 'image') }}' }">
    @csrf

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label class="label">Campaign Name *</label>
            <input type="text" name="name" value="{{ old('name', $p['name'] ?? '') }}" required class="input" placeholder="META | KASHMIR | PACKAGE | LEADS | SEP-2026">
        </div>
        <div>
            <label class="label">Platform *</label>
            <select name="provider" class="input" required>
                <option value="meta_ads" @selected(old('provider', $p['provider'] ?? '')==='meta_ads')>Meta Ads</option>
                <option value="google_ads" @selected(old('provider', $p['provider'] ?? '')==='google_ads')>Google Ads</option>
            </select>
        </div>
        <div>
            <label class="label">Ad Account</label>
            <select name="account_id" class="input">
                <option value="">— select after connecting —</option>
                @foreach ($accounts as $acc)<option value="{{ $acc->id }}" @selected((string)old('account_id')===(string)$acc->id)>{{ $acc->account_name }} ({{ ucfirst(str_replace('_',' ',$acc->provider)) }})</option>@endforeach
            </select>
        </div>
        <div>
            <label class="label">Objective</label>
            <select name="objective" class="input">
                @foreach ($objectives as $o)<option value="{{ $o }}" @selected(old('objective', $p['objective'] ?? '')===$o)>{{ $o }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="label">Destination</label>
            <input type="text" name="destination" value="{{ old('destination', $p['destination'] ?? '') }}" class="input" placeholder="Kashmir">
        </div>
    </div>

    {{-- Product link --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label class="label">Link Product (package)</label>
            <select name="product_id" class="input">
                <option value="">None / custom</option>
                @foreach ($packages as $pkg)<option value="{{ $pkg->id }}" @selected((string)old('product_id', $p['product_id'] ?? '')===(string)$pkg->id)>{{ $pkg->name }}</option>@endforeach
            </select>
            <input type="hidden" name="product_type" value="{{ old('product_type', $p['product_type'] ?? 'package') }}">
        </div>
        <div>
            <label class="label">Landing Page URL</label>
            <input type="url" name="landing_page" value="{{ old('landing_page', $p['landing_page'] ?? '') }}" class="input" placeholder="https://…">
        </div>
    </div>

    {{-- Budget --}}
    <div class="rounded-lg border bg-ink-50 p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="label">Budget Type *</label>
                <select name="budget_type" x-model="budgetType" class="input">
                    <option value="daily">Daily</option>
                    <option value="lifetime">Lifetime</option>
                </select>
            </div>
            <div x-show="budgetType==='daily'">
                <label class="label">Daily Budget (₹)</label>
                <input type="number" step="0.01" min="0" name="daily_budget" value="{{ old('daily_budget') }}" class="input">
            </div>
            <div x-show="budgetType==='lifetime'" x-cloak>
                <label class="label">Lifetime Budget (₹)</label>
                <input type="number" step="0.01" min="0" name="lifetime_budget" value="{{ old('lifetime_budget') }}" class="input">
            </div>
            <div>
                <label class="label">Bidding</label>
                <input type="text" name="bidding_strategy" value="{{ old('bidding_strategy') }}" class="input" placeholder="Maximize Conversions">
            </div>
        </div>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div><label class="label">Start Date</label><input type="date" name="start_at" value="{{ old('start_at') }}" class="input"></div>
            <div><label class="label">End Date</label><input type="date" name="end_at" value="{{ old('end_at') }}" class="input"></div>
        </div>
    </div>

    {{-- KPI targets --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div><label class="label">Target CPL (₹)</label><input type="number" step="0.01" name="target_cpl" value="{{ old('target_cpl') }}" class="input"></div>
        <div><label class="label">Target ROAS (x)</label><input type="number" step="0.1" name="target_roas" value="{{ old('target_roas') }}" class="input"></div>
        <div><label class="label">Target Leads</label><input type="number" name="target_leads" value="{{ old('target_leads') }}" class="input"></div>
    </div>

    <input type="hidden" name="currency" value="INR">

    {{-- ───────────── Ad Set: targeting (Meta) ───────────── --}}
    <div class="rounded-lg border border-brand-100 bg-brand-50/30 p-4">
        <h3 class="text-sm font-bold">Ad Set — Targeting <span class="text-[11px] font-normal text-ink-400">(Meta)</span></h3>
        <p class="mb-3 text-[11px] text-ink-400">Used when publishing to Meta to build the ad set. The daily budget above funds this ad set.</p>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div><label class="label">Age min</label><input type="number" name="age_min" min="13" max="65" value="{{ old('age_min', 18) }}" class="input"></div>
            <div><label class="label">Age max</label><input type="number" name="age_max" min="13" max="65" value="{{ old('age_max', 65) }}" class="input"></div>
            <div class="col-span-2">
                <label class="label">Gender</label>
                <div class="flex items-center gap-3 pt-2 text-sm">
                    <label class="flex items-center gap-1"><input type="checkbox" name="genders[]" value="1" @checked(in_array('1', old('genders', [])))> Male</label>
                    <label class="flex items-center gap-1"><input type="checkbox" name="genders[]" value="2" @checked(in_array('2', old('genders', [])))> Female</label>
                    <span class="text-[11px] text-ink-400">(none = all)</span>
                </div>
            </div>
        </div>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Countries (ISO codes)</label>
                <input type="text" name="countries" value="{{ old('countries', 'IN') }}" class="input" placeholder="IN, AE, US">
            </div>
            <div>
                <label class="label">Interests (keywords)</label>
                <input type="text" name="interests" value="{{ old('interests') }}" class="input" placeholder="Travel, Adventure travel, Honeymoon">
                <p class="mt-1 text-[11px] text-ink-400">Resolved to Meta interests at publish; unmatched keywords are skipped.</p>
            </div>
        </div>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Optimization goal</label>
                <select name="optimization_goal" class="input">
                    @foreach (['LINK_CLICKS' => 'Link clicks', 'REACH' => 'Reach', 'IMPRESSIONS' => 'Impressions', 'POST_ENGAGEMENT' => 'Engagement', 'LANDING_PAGE_VIEWS' => 'Landing page views'] as $v => $lbl)
                        <option value="{{ $v }}" @selected(old('optimization_goal')===$v)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Billing event</label>
                <select name="billing_event" class="input">
                    <option value="IMPRESSIONS" @selected(old('billing_event','IMPRESSIONS')==='IMPRESSIONS')>Impressions</option>
                    <option value="LINK_CLICKS" @selected(old('billing_event')==='LINK_CLICKS')>Link clicks</option>
                </select>
            </div>
        </div>
    </div>

    {{-- ───────────── Ad Creative (Meta) ───────────── --}}
    <div class="rounded-lg border border-brand-100 bg-brand-50/30 p-4">
        <h3 class="text-sm font-bold">Ad Creative <span class="text-[11px] font-normal text-ink-400">(Meta)</span></h3>
        <p class="mb-3 text-[11px] text-ink-400">The image ad shown to people. Requires a Facebook Page ID and one image.</p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Facebook Page ID *</label>
                <input type="text" name="page_id" value="{{ old('page_id') }}" class="input" placeholder="1234567890">
            </div>
            <div>
                <label class="label">Call to action</label>
                <select name="cta" class="input">
                    @foreach (['LEARN_MORE' => 'Learn more', 'BOOK_TRAVEL' => 'Book now', 'GET_QUOTE' => 'Get quote', 'CONTACT_US' => 'Contact us', 'SIGN_UP' => 'Sign up'] as $v => $lbl)
                        <option value="{{ $v }}" @selected(old('cta','LEARN_MORE')===$v)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-3">
            <label class="label">Primary text</label>
            <textarea name="primary_text" rows="2" class="input" placeholder="Discover Kashmir with curated packages…">{{ old('primary_text') }}</textarea>
        </div>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div><label class="label">Headline</label><input type="text" name="headline" value="{{ old('headline') }}" class="input" placeholder="7-Day Kashmir Escape"></div>
            <div><label class="label">Description</label><input type="text" name="description" value="{{ old('description') }}" class="input" placeholder="Handpicked hotels & sightseeing"></div>
        </div>

        {{-- Creative type: image or video --}}
        <div class="mt-3">
            <label class="label">Creative type</label>
            <div class="flex items-center gap-4 pt-1 text-sm">
                <label class="flex items-center gap-1"><input type="radio" name="creative_type" value="image" x-model="creativeType"> Image</label>
                <label class="flex items-center gap-1"><input type="radio" name="creative_type" value="video" x-model="creativeType"> Video</label>
            </div>
        </div>

        {{-- Image creative --}}
        <div class="mt-3" x-show="creativeType==='image'">
            <label class="label">Image</label>
            <div class="mb-2 flex items-center gap-3 text-sm">
                <label class="flex items-center gap-1"><input type="radio" name="img_source" value="upload" x-model="imgSource"> Upload</label>
                <label class="flex items-center gap-1"><input type="radio" name="img_source" value="package" x-model="imgSource"> Package image URL</label>
            </div>
            <div x-show="imgSource==='upload'">
                <input type="file" name="creative_image" accept="image/*" class="input">
                <p class="mt-1 text-[11px] text-ink-400">JPG/PNG up to 5MB. 1200×628 or 1080×1080 works best.</p>
            </div>
            <div x-show="imgSource==='package'" x-cloak>
                <input type="url" name="package_image_url" value="{{ old('package_image_url') }}" class="input" placeholder="https://…/package-photo.jpg">
                <p class="mt-1 text-[11px] text-ink-400">Paste a public image URL (e.g. a package photo).</p>
            </div>
        </div>

        {{-- Video creative --}}
        <div class="mt-3" x-show="creativeType==='video'" x-cloak>
            <label class="label">Video</label>
            <input type="file" name="creative_video" accept="video/mp4,video/quicktime" class="input">
            <p class="mt-1 text-[11px] text-ink-400">MP4 recommended, up to 150MB. Meta processes the video after upload — if it's still processing when you publish, just click Publish again and it resumes.</p>
            <div class="mt-2">
                <label class="label">Thumbnail (optional)</label>
                <input type="file" name="creative_image" accept="image/*" class="input">
                <p class="mt-1 text-[11px] text-ink-400">Leave empty to let Meta auto-pick a thumbnail from the video.</p>
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-2 border-t pt-4">
        <a href="{{ route('admin.marketing.campaigns.index') }}" class="btn-ghost btn-sm">Cancel</a>
        <button class="btn-primary btn-sm">Save Draft</button>
    </div>
</form>
@endsection
